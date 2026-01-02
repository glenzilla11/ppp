from flask import Flask, render_template, jsonify, request
from flask_cors import CORS
import win32print
import win32ui
from datetime import datetime
import pymysql
import pymysql.cursors
import requests
import json
import base64
import urllib3

# Disable SSL warnings (for sandbox testing)
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

app = Flask(__name__)
CORS(app)

# ================================================================
# MYSQL DATABASE CONFIGURATION
# ================================================================
DB_CONFIG = {
    'host': 'localhost',
    'user': 'kireru',
    'passwd': 'kireru',
    'db': 'zilla',
    'charset': 'utf8mb4'
}

# ================================================================
# M-PESA CONFIGURATION (Daraja API - Sandbox)
# Using the WORKING credentials from PHP
# ================================================================
MPESA_CONFIG = {
    'env': 'sandbox',
    'consumer_key': 'fc3S6LRQIvtAXMtDOkULAAJdYCSrOLMjaG7IISlhXZe60iYs',
    'consumer_secret': 'hib6HfvrtHsGpzQx7Ijk33wc1QpUdsgp24HhthgXhviQOKL37Id9LqsOATj70mIk',
    'shortcode': '174379',
    'passkey': 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
    'callback_url': 'http://localhost:5000/api/mpesa_callback'
}

if MPESA_CONFIG['env'] == 'sandbox':
    MPESA_CONFIG['auth_url'] = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
    MPESA_CONFIG['stk_url'] = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
    MPESA_CONFIG['query_url'] = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
else:
    MPESA_CONFIG['auth_url'] = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
    MPESA_CONFIG['stk_url'] = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
    MPESA_CONFIG['query_url'] = 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query'

def get_db():
    """Get database connection"""
    try:
        conn = pymysql.connect(
            host=DB_CONFIG['host'],
            user=DB_CONFIG['user'],
            password=DB_CONFIG['passwd'],
            database=DB_CONFIG['db'],
            charset=DB_CONFIG['charset']
        )
        return conn
    except pymysql.Error as e:
        print(f"Database connection error: {e}")
        return None

# ================================================================
# M-PESA API CLASS (Ported from PHP)
# ================================================================

class MpesaAPI:
    """M-PESA API Helper - Ported from working PHP code"""
    
    @staticmethod
    def get_access_token():
        """Get OAuth access token - exactly like PHP"""
        try:
            # Create auth string exactly like PHP
            auth_string = MPESA_CONFIG['consumer_key'] + ':' + MPESA_CONFIG['consumer_secret']
            auth = base64.b64encode(auth_string.encode()).decode()
            
            headers = {
                'Authorization': 'Basic ' + auth,
                'Content-Type': 'application/json'
            }
            
            print(f"\n🔐 Requesting M-PESA Access Token...")
            
            response = requests.get(
                MPESA_CONFIG['auth_url'],
                headers=headers,
                timeout=30,
                verify=False  # Same as PHP CURLOPT_SSL_VERIFYPEER => false
            )
            
            print(f"   HTTP Status: {response.status_code}")
            
            if response.status_code != 200:
                print(f"   ✗ Error Response: {response.text}")
                return None
            
            result = response.json()
            
            if 'access_token' not in result:
                print(f"   ✗ No access_token in response: {result}")
                return None
            
            print(f"✓ Token obtained")
            return result['access_token']
            
        except Exception as e:
            print(f"✗ M-PESA Auth Error: {e}")
            return None
    
    @staticmethod
    def normalize_phone(phone):
        """Normalize phone number to 254 format - exactly like PHP"""
        import re
        # Remove all non-digits
        phone = re.sub(r'[^0-9]', '', str(phone))
        
        # Convert to 254 format
        if phone.startswith('0'):
            phone = '254' + phone[1:]
        elif not phone.startswith('254'):
            phone = '254' + phone
        
        return phone
    
    @staticmethod
    def stk_push(phone, amount):
        """Send STK Push Request - exactly like PHP"""
        token = MpesaAPI.get_access_token()
        
        if not token:
            return {'success': False, 'message': 'Failed to get M-Pesa access token'}
        
        phone = MpesaAPI.normalize_phone(phone)
        timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
        
        # Password generation - EXACTLY like PHP:
        # $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
        password_string = MPESA_CONFIG['shortcode'] + MPESA_CONFIG['passkey'] + timestamp
        password = base64.b64encode(password_string.encode()).decode()
        
        # Amount must be integer, rounded up
        import math
        amount = int(math.ceil(float(amount)))
        
        payload = {
            'BusinessShortCode': MPESA_CONFIG['shortcode'],
            'Password': password,
            'Timestamp': timestamp,
            'TransactionType': 'CustomerPayBillOnline',
            'Amount': amount,
            'PartyA': phone,
            'PartyB': MPESA_CONFIG['shortcode'],
            'PhoneNumber': phone,
            'CallBackURL': MPESA_CONFIG['callback_url'],
            'AccountReference': 'ChairmanPOS',
            'TransactionDesc': 'Payment'
        }
        
        headers = {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
        
        print(f"\n💳 Sending STK Push...")
        print(f"   Phone: {phone}")
        print(f"   Amount: {amount}")
        print(f"   Timestamp: {timestamp}")
        print(f"   Shortcode: {MPESA_CONFIG['shortcode']}")
        
        try:
            response = requests.post(
                MPESA_CONFIG['stk_url'],
                json=payload,
                headers=headers,
                timeout=30,
                verify=False
            )
            
            print(f"   HTTP Status: {response.status_code}")
            print(f"   Response: {response.text}")
            
            result = response.json()
            
            # Check for success - ResponseCode should be '0'
            if result.get('ResponseCode') == '0':
                return {
                    'success': True,
                    'checkout_request_id': result.get('CheckoutRequestID'),
                    'merchant_request_id': result.get('MerchantRequestID'),
                    'response_description': result.get('ResponseDescription', 'Success')
                }
            
            return {
                'success': False,
                'message': result.get('errorMessage') or result.get('ResponseDescription') or 'STK push failed',
                'response_code': result.get('ResponseCode') or result.get('errorCode') or 'ERROR'
            }
            
        except Exception as e:
            print(f"✗ M-PESA STK Error: {e}")
            return {'success': False, 'message': f'Connection error: {str(e)}'}
    
    @staticmethod
    def check_status(checkout_request_id):
        """Check STK Push Status - exactly like PHP"""
        token = MpesaAPI.get_access_token()
        
        if not token:
            return {'success': False, 'status': 'error', 'message': 'Failed to get access token'}
        
        timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
        password_string = MPESA_CONFIG['shortcode'] + MPESA_CONFIG['passkey'] + timestamp
        password = base64.b64encode(password_string.encode()).decode()
        
        payload = {
            'BusinessShortCode': MPESA_CONFIG['shortcode'],
            'Password': password,
            'Timestamp': timestamp,
            'CheckoutRequestID': checkout_request_id
        }
        
        headers = {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
        
        try:
            response = requests.post(
                MPESA_CONFIG['query_url'],
                json=payload,
                headers=headers,
                timeout=30,
                verify=False
            )
            
            print(f"   Query HTTP Status: {response.status_code}")
            print(f"   Query Response: {response.text}")
            
            result = response.json()
            
            # Check ResultCode
            result_code = str(result.get('ResultCode', ''))
            
            if result_code == '0':
                # Success - payment completed
                return {
                    'success': True,
                    'status': 'completed',
                    'mpesa_receipt': result.get('MpesaReceiptNumber', 'N/A'),
                    'message': 'Payment successful'
                }
            elif result_code == '1032':
                # User cancelled
                return {
                    'success': False,
                    'status': 'cancelled',
                    'message': 'Transaction cancelled by user'
                }
            elif result_code == '1037':
                # Timeout
                return {
                    'success': False,
                    'status': 'timeout',
                    'message': 'Transaction timed out'
                }
            elif result_code:
                # Other failure
                return {
                    'success': False,
                    'status': 'failed',
                    'message': result.get('ResultDesc', 'Payment failed')
                }
            
            # Still processing (no ResultCode yet)
            return {
                'success': True,
                'status': 'pending',
                'message': 'Waiting for payment confirmation'
            }
            
        except Exception as e:
            print(f"✗ M-PESA Query Error: {e}")
            return {'success': False, 'status': 'error', 'message': f'Connection error: {str(e)}'}


# ================================================================
# PRINTER FUNCTIONS
# ================================================================

def get_printers():
    """Get list of available printers"""
    printers = []
    for printer in win32print.EnumPrinters(win32print.PRINTER_ENUM_LOCAL | win32print.PRINTER_ENUM_CONNECTIONS):
        printers.append(printer[2])
    return printers

def print_to_thermal(printer_name, receipt_text):
    """Print raw text to thermal printer"""
    try:
        ESC = b'\x1b'
        GS = b'\x1d'
        
        raw_data = b''
        raw_data += ESC + b'@'  # Initialize
        raw_data += receipt_text.encode('cp437', errors='replace')
        raw_data += b'\n\n\n\n'  # Feed
        raw_data += GS + b'V' + b'\x00'  # Cut paper
        
        hPrinter = win32print.OpenPrinter(printer_name)
        
        try:
            hJob = win32print.StartDocPrinter(hPrinter, 1, ("POS Receipt", None, "RAW"))
            
            try:
                win32print.StartPagePrinter(hPrinter)
                win32print.WritePrinter(hPrinter, raw_data)
                win32print.EndPagePrinter(hPrinter)
            finally:
                win32print.EndDocPrinter(hPrinter)
                
        finally:
            win32print.ClosePrinter(hPrinter)
            
        return True, "Printed successfully"
        
    except Exception as e:
        return False, str(e)

def generate_receipt_text(cart, total, paid, change, payment_type):
    """Generate receipt text"""
    now = datetime.now()
    
    lines = []
    lines.append("=" * 32)
    lines.append("        CHAIRMAN POS")
    lines.append("      Your Trusted Store")
    lines.append("      Tel: 0700-000-000")
    lines.append("=" * 32)
    lines.append(f"Date: {now.strftime('%d/%m/%Y %H:%M:%S')}")
    lines.append(f"Cashier: rapid")
    lines.append("-" * 32)
    lines.append(f"{'ITEM':<16}{'QTY':>4}{'PRICE':>12}")
    lines.append("-" * 32)
    
    for item in cart:
        name = item['description'][:16]
        qty = str(item['qty'])
        price = f"{item['subtotal']:.2f}"
        lines.append(f"{name:<16}{qty:>4}{price:>12}")
    
    lines.append("-" * 32)
    lines.append(f"{'SUBTOTAL:':<20}{total:>12.2f}")
    lines.append(f"{'TOTAL:':<20}{total:>12.2f}")
    lines.append("=" * 32)
    lines.append(f"{'Payment:':<12}{payment_type.upper():>20}")
    lines.append(f"{'Paid:':<20}{paid:>12.2f}")
    lines.append(f"{'CHANGE:':<20}{change:>12.2f}")
    lines.append("=" * 32)
    lines.append("")
    lines.append("      Thank you for shopping!")
    lines.append("        Please come again")
    lines.append("")
    lines.append("=" * 32)
    
    return '\n'.join(lines)


# ================================================================
# ROUTES
# ================================================================

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/admin')
def admin():
    return render_template('admin.html')

@app.route('/api/printers', methods=['GET'])
def list_printers():
    """List available printers"""
    try:
        printers = get_printers()
        default = win32print.GetDefaultPrinter()
        return jsonify({
            'success': True,
            'printers': printers,
            'default': default
        })
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/api/products', methods=['GET'])
def get_products():
    """Get all products"""
    try:
        conn = get_db()
        if not conn:
            return jsonify({'success': False, 'error': 'Database connection failed'})
        
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        try:
            cursor.execute("""
                SELECT * FROM products
                WHERE is_active = 1
                ORDER BY name
            """)
            
            products = cursor.fetchall()
            return jsonify({
                'success': True,
                'products': products
            })
            
        finally:
            cursor.close()
            conn.close()
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


# ================================================================
# M-PESA ENDPOINTS
# ================================================================

@app.route('/api/mpesa_stk', methods=['POST'])
def mpesa_stk():
    """Initiate M-PESA STK Push - ported from PHP"""
    print("\n" + "=" * 50)
    print("📱 API ENDPOINT: /api/mpesa_stk")
    print("=" * 50)
    
    try:
        data = request.json
        print(f"\n📥 Request data: {data}")
        
        phone = data.get('phone_number', '')
        amount = data.get('amount', 0)
        cart = data.get('cart', [])
        cashier_id = data.get('cashier_id', 1)
        
        # Clean and validate phone
        import re
        phone = re.sub(r'[^0-9]', '', str(phone))
        
        if not re.match(r'^(254|0)(7|1)\d{8}$', phone):
            return jsonify({
                'success': False, 
                'message': 'Invalid phone number. Use format: 07XXXXXXXX'
            }), 400
        
        # Validate amount
        if float(amount) < 1:
            return jsonify({
                'success': False, 
                'message': 'Amount must be at least KES 1'
            }), 400
        
        print(f"   Phone: {phone}")
        print(f"   Amount: {amount}")
        print(f"   Cart items: {len(cart)}")
        print(f"   Cashier ID: {cashier_id}")
        
        # Create sale record first
        conn = get_db()
        if not conn:
            return jsonify({'success': False, 'error': 'Database connection failed'}), 500
        
        cursor = conn.cursor()
        sale_id = None
        
        try:
            receipt_number = f"RCP{datetime.now().strftime('%Y%m%d%H%M%S')}"
            
            cursor.execute("""
                INSERT INTO sales 
                (receipt_number, cashier_id, subtotal, total, paid_amount, change_amount, 
                 payment_method, transaction_status)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (receipt_number, cashier_id, amount, amount, 0, 0, 'mpesa', 'pending'))
            
            sale_id = cursor.lastrowid
            
            for item in cart:
                cursor.execute("""
                    INSERT INTO sale_items 
                    (sale_id, product_id, quantity, unit_price, subtotal)
                    VALUES (%s, %s, %s, %s, %s)
                """, (
                    sale_id,
                    item.get('id'),
                    item.get('qty', 1),
                    item.get('price', 0),
                    item.get('subtotal', 0)
                ))
            
            conn.commit()
            print(f"✓ Sale created: ID {sale_id}")
            
        except pymysql.Error as e:
            conn.rollback()
            print(f"✗ Database error: {e}")
            return jsonify({'success': False, 'error': f'Database error: {str(e)}'}), 500
        finally:
            cursor.close()
            conn.close()
        
        # Send STK Push using the MpesaAPI class
        result = MpesaAPI.stk_push(phone, amount)
        
        if not result['success']:
            # Mark sale as failed
            mark_sale_failed(sale_id)
            print(f"❌ STK Push failed: {result.get('message')}")
            return jsonify({
                'success': False, 
                'message': result.get('message', 'Failed to send STK push')
            }), 400
        
        # Store in mpesa_transactions table
        conn = get_db()
        if conn:
            cursor = conn.cursor()
            try:
                cursor.execute("""
                    INSERT INTO mpesa_transactions 
                    (sale_id, checkout_request_id, merchant_request_id, phone_number, amount, status, created_at)
                    VALUES (%s, %s, %s, %s, %s, %s, NOW())
                """, (
                    sale_id,
                    result.get('checkout_request_id'),
                    result.get('merchant_request_id'),
                    MpesaAPI.normalize_phone(phone),
                    amount,
                    'pending'
                ))
                conn.commit()
                print(f"✓ Transaction saved to database")
            except pymysql.Error as e:
                print(f"Database insert error: {e}")
            finally:
                cursor.close()
                conn.close()
        
        print(f"✓ STK push sent successfully!")
        
        return jsonify({
            'success': True,
            'sale_id': sale_id,
            'checkout_request_id': result.get('checkout_request_id'),
            'merchant_request_id': result.get('merchant_request_id'),
            'message': 'STK push sent. Check your phone.'
        })
        
    except Exception as e:
        print(f"❌ Exception: {str(e)}")
        import traceback
        traceback.print_exc()
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/api/mpesa_status/<checkout_request_id>', methods=['GET'])
def mpesa_status(checkout_request_id):
    """Check M-PESA payment status - ported from PHP"""
    print(f"\n🔍 Checking M-PESA status for: {checkout_request_id}")
    
    try:
        # First check local database
        conn = get_db()
        if conn:
            cursor = conn.cursor(pymysql.cursors.DictCursor)
            try:
                cursor.execute("""
                    SELECT status, mpesa_receipt_number, result_code, result_desc 
                    FROM mpesa_transactions 
                    WHERE checkout_request_id = %s
                    LIMIT 1
                """, (checkout_request_id,))
                
                transaction = cursor.fetchone()
                
                if transaction:
                    if transaction['status'] == 'completed':
                        return jsonify({
                            'success': True,
                            'status': 'completed',
                            'mpesa_receipt': transaction['mpesa_receipt_number']
                        })
                    elif transaction['status'] in ['failed', 'cancelled']:
                        return jsonify({
                            'success': False,
                            'status': transaction['status'],
                            'message': transaction['result_desc'] or 'Payment not successful'
                        })
            finally:
                cursor.close()
                conn.close()
        
        # If not in DB yet, query M-Pesa API
        result = MpesaAPI.check_status(checkout_request_id)
        
        # If successful from API, save to DB
        if result.get('success') and result.get('status') == 'completed':
            conn = get_db()
            if conn:
                cursor = conn.cursor()
                try:
                    cursor.execute("""
                        UPDATE mpesa_transactions 
                        SET status = %s, mpesa_receipt_number = %s, result_code = '0', result_desc = 'Success'
                        WHERE checkout_request_id = %s
                    """, ('completed', result.get('mpesa_receipt', ''), checkout_request_id))
                    conn.commit()
                except Exception as e:
                    print(f"DB Update Error: {e}")
                finally:
                    cursor.close()
                    conn.close()
        
        return jsonify(result)
        
    except Exception as e:
        print(f"Error checking status: {e}")
        return jsonify({'success': False, 'status': 'error', 'message': str(e)}), 500


@app.route('/api/mpesa_callback', methods=['POST'])
def mpesa_callback():
    """M-PESA Callback Handler - ported from PHP"""
    print("\n" + "=" * 50)
    print("📲 M-PESA CALLBACK RECEIVED")
    print("=" * 50)
    
    try:
        data = request.json
        print(f"Callback data: {json.dumps(data, indent=2)}")
        
        if not data or 'Body' not in data:
            return jsonify({'ResultCode': 0, 'ResultDesc': 'Accepted'})
        
        body = data.get('Body', {}).get('stkCallback', data.get('Body', {}))
        result_code = body.get('ResultCode')
        result_desc = body.get('ResultDesc', 'Unknown error')
        checkout_request_id = body.get('CheckoutRequestID')
        
        print(f"   CheckoutRequestID: {checkout_request_id}")
        print(f"   ResultCode: {result_code}")
        print(f"   ResultDesc: {result_desc}")
        
        if result_code == 0 or result_code == '0':
            # Payment successful
            callback_metadata = body.get('CallbackMetadata', {}).get('Item', [])
            
            mpesa_receipt = ''
            amount = ''
            phone = ''
            
            for item in callback_metadata:
                name = item.get('Name', '')
                value = item.get('Value', '')
                
                if name == 'MpesaReceiptNumber':
                    mpesa_receipt = value
                elif name == 'Amount':
                    amount = value
                elif name == 'PhoneNumber':
                    phone = value
            
            print(f"   M-PESA Receipt: {mpesa_receipt}")
            print(f"   Amount: {amount}")
            print(f"   Phone: {phone}")
            
            # Update database
            conn = get_db()
            if conn:
                cursor = conn.cursor()
                try:
                    # Update mpesa_transactions
                    cursor.execute("""
                        UPDATE mpesa_transactions 
                        SET status = 'completed', 
                            mpesa_receipt_number = %s,
                            result_code = '0',
                            result_desc = 'Success'
                        WHERE checkout_request_id = %s
                    """, (mpesa_receipt, checkout_request_id))
                    
                    # Get sale_id and update sales table
                    cursor.execute("""
                        SELECT sale_id FROM mpesa_transactions 
                        WHERE checkout_request_id = %s
                    """, (checkout_request_id,))
                    row = cursor.fetchone()
                    
                    if row:
                        sale_id = row[0]
                        cursor.execute("""
                            UPDATE sales 
                            SET transaction_status = 'completed',
                                paid_amount = %s,
                                mpesa_receipt_number = %s
                            WHERE id = %s
                        """, (amount, mpesa_receipt, sale_id))
                    
                    conn.commit()
                    print(f"✓ Transaction confirmed in database")
                except Exception as e:
                    print(f"DB Error: {e}")
                finally:
                    cursor.close()
                    conn.close()
        else:
            # Payment failed/cancelled
            status = 'failed'
            if result_code == 1032 or result_code == '1032':
                status = 'cancelled'
            elif result_code == 1037 or result_code == '1037':
                status = 'timeout'
            
            conn = get_db()
            if conn:
                cursor = conn.cursor()
                try:
                    cursor.execute("""
                        UPDATE mpesa_transactions 
                        SET status = %s, result_code = %s, result_desc = %s
                        WHERE checkout_request_id = %s
                    """, (status, str(result_code), result_desc, checkout_request_id))
                    
                    cursor.execute("""
                        SELECT sale_id FROM mpesa_transactions 
                        WHERE checkout_request_id = %s
                    """, (checkout_request_id,))
                    row = cursor.fetchone()
                    
                    if row:
                        cursor.execute("""
                            UPDATE sales SET transaction_status = 'failed' WHERE id = %s
                        """, (row[0],))
                    
                    conn.commit()
                except Exception as e:
                    print(f"DB Error: {e}")
                finally:
                    cursor.close()
                    conn.close()
        
        # Always return success to M-Pesa
        return jsonify({'ResultCode': 0, 'ResultDesc': 'Accepted'})
        
    except Exception as e:
        print(f"❌ Callback error: {e}")
        return jsonify({'ResultCode': 0, 'ResultDesc': 'Accepted'})


def mark_sale_failed(sale_id):
    """Mark a sale as failed"""
    if not sale_id:
        return
    
    conn = get_db()
    if conn:
        cursor = conn.cursor()
        try:
            cursor.execute("""
                UPDATE sales 
                SET transaction_status = 'failed'
                WHERE id = %s
            """, (sale_id,))
            conn.commit()
        except pymysql.Error as e:
            print(f"Error marking sale as failed: {e}")
        finally:
            cursor.close()
            conn.close()


# ================================================================
# PRINT & CASH SALE ENDPOINTS
# ================================================================

@app.route('/api/print', methods=['POST'])
def print_receipt():
    """Print receipt for CASH payments"""
    try:
        data = request.json
        printer_name = data.get('printer', win32print.GetDefaultPrinter())
        cart = data.get('cart', [])
        total = data.get('total', 0)
        paid = data.get('paid', 0)
        change = data.get('change', 0)
        payment_type = data.get('paymentType', 'cash')
        cashier_id = data.get('cashier_id', 1)
        
        if payment_type != 'cash':
            return jsonify({'success': False, 'error': 'Use appropriate endpoint for this payment method'})
        
        receipt = generate_receipt_text(cart, total, paid, change, payment_type)
        success, message = print_to_thermal(printer_name, receipt)
        
        if success:
            conn = get_db()
            if not conn:
                return jsonify({'success': False, 'error': 'Database connection failed'})
            
            cursor = conn.cursor()
            try:
                receipt_number = f"RCP{datetime.now().strftime('%Y%m%d%H%M%S')}"
                
                cursor.execute("""
                    INSERT INTO sales 
                    (receipt_number, cashier_id, subtotal, total, paid_amount, change_amount, 
                     payment_method, transaction_status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """, (receipt_number, cashier_id, total, total, paid, change, payment_type, 'completed'))
                
                sale_id = cursor.lastrowid
                
                for item in cart:
                    cursor.execute("""
                        INSERT INTO sale_items 
                        (sale_id, product_id, quantity, unit_price, subtotal)
                        VALUES (%s, %s, %s, %s, %s)
                    """, (
                        sale_id,
                        item.get('id'),
                        item.get('qty', 1),
                        item.get('price', 0),
                        item.get('subtotal', 0)
                    ))
                
                conn.commit()
                
                return jsonify({
                    'success': True,
                    'message': message,
                    'sale_id': sale_id,
                    'receipt_number': receipt_number
                })
                
            except pymysql.Error as e:
                conn.rollback()
                return jsonify({'success': False, 'error': str(e)})
            finally:
                cursor.close()
                conn.close()
        else:
            return jsonify({'success': False, 'error': message})
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/test-print', methods=['POST'])
def test_print():
    """Test print"""
    try:
        data = request.json
        printer_name = data.get('printer', win32print.GetDefaultPrinter())
        
        test_receipt = f"""
================================
        CHAIRMAN POS
      ** TEST PRINT **
================================
Printer: {printer_name}
Date: {datetime.now().strftime('%d/%m/%Y %H:%M')}

If you can read this,
your printer is working!

================================
"""
        
        success, message = print_to_thermal(printer_name, test_receipt)
        
        return jsonify({
            'success': success,
            'message': message
        })
        
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


# ================================================================
# ADMIN ENDPOINTS
# ================================================================

@app.route('/api/admin/products', methods=['GET'])
def admin_get_products():
    """Get all products for admin panel"""
    try:
        conn = get_db()
        if not conn:
            return jsonify({'success': False, 'error': 'Database connection failed'})
        
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        try:
            cursor.execute("SELECT * FROM products ORDER BY name")
            products = cursor.fetchall()
            return jsonify({'success': True, 'products': products})
        finally:
            cursor.close()
            conn.close()
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/admin/products', methods=['POST'])
def admin_create_product():
    """Create a new product"""
    try:
        data = request.json
        conn = get_db()
        if not conn:
            return jsonify({'success': False, 'error': 'Database connection failed'})
        
        cursor = conn.cursor()
        try:
            cursor.execute("""
                INSERT INTO products 
                (code, name, description, category, price, cost_price, 
                 quantity_in_stock, unit, barcode, is_active)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                data.get('code'),
                data.get('name'),
                data.get('description'),
                data.get('category'),
                data.get('price'),
                data.get('cost_price'),
                data.get('quantity_in_stock', 0),
                data.get('unit'),
                data.get('barcode'),
                1
            ))
            
            conn.commit()
            product_id = cursor.lastrowid
            
            return jsonify({
                'success': True,
                'message': 'Product created',
                'product_id': product_id
            })
            
        finally:
            cursor.close()
            conn.close()
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/admin/products/<int:product_id>', methods=['PUT'])
def admin_update_product(product_id):
    """Update a product"""
    try:
        data = request.json
        conn = get_db()
        if not conn:
            return jsonify({'success': False, 'error': 'Database connection failed'})
        
        cursor = conn.cursor()
        try:
            cursor.execute("""
                UPDATE products 
                SET code=%s, name=%s, description=%s, category=%s, 
                    price=%s, cost_price=%s, quantity_in_stock=%s, 
                    unit=%s, barcode=%s
                WHERE id=%s
            """, (
                data.get('code'),
                data.get('name'),
                data.get('description'),
                data.get('category'),
                data.get('price'),
                data.get('cost_price'),
                data.get('quantity_in_stock'),
                data.get('unit'),
                data.get('barcode'),
                product_id
            ))
            
            conn.commit()
            return jsonify({'success': True, 'message': 'Product updated'})
            
        finally:
            cursor.close()
            conn.close()
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/admin/products/<int:product_id>', methods=['DELETE'])
def admin_delete_product(product_id):
    """Delete a product"""
    try:
        conn = get_db()
        if not conn:
            return jsonify({'success': False, 'error': 'Database connection failed'})
        
        cursor = conn.cursor()
        try:
            cursor.execute("DELETE FROM products WHERE id=%s", (product_id,))
            conn.commit()
            return jsonify({'success': True, 'message': 'Product deleted'})
        finally:
            cursor.close()
            conn.close()
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/admin/sales', methods=['GET'])
def admin_get_sales():
    """Get all sales for admin panel"""
    try:
        conn = get_db()
        if not conn:
            return jsonify({'success': False, 'error': 'Database connection failed'})
        
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        try:
            cursor.execute("""
                SELECT s.*, u.username as cashier_name
                FROM sales s
                LEFT JOIN users u ON s.cashier_id = u.id
                ORDER BY s.created_at DESC
                LIMIT 1000
            """)
            
            sales = cursor.fetchall()
            return jsonify({'success': True, 'sales': sales})
            
        finally:
            cursor.close()
            conn.close()
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/admin/stock', methods=['POST'])
def admin_adjust_stock():
    """Adjust product stock"""
    try:
        data = request.json
        conn = get_db()
        if not conn:
            return jsonify({'success': False, 'error': 'Database connection failed'})
        
        cursor = conn.cursor()
        try:
            product_id = data.get('product_id')
            quantity = data.get('quantity', 0)
            adjustment_type = data.get('type', 'inventory')
            reason = data.get('reason', '')
            user_id = data.get('user_id', 1)
            
            cursor.execute("""
                UPDATE products 
                SET quantity_in_stock = quantity_in_stock + %s
                WHERE id = %s
            """, (quantity, product_id))
            
            cursor.execute("""
                INSERT INTO stock_adjustments 
                (product_id, adjustment_type, quantity_adjusted, reason, created_by)
                VALUES (%s, %s, %s, %s, %s)
            """, (product_id, adjustment_type, quantity, reason, user_id))
            
            conn.commit()
            return jsonify({'success': True, 'message': 'Stock adjusted'})
            
        finally:
            cursor.close()
            conn.close()
            
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


# ================================================================
# MAIN
# ================================================================

if __name__ == '__main__':
    print("=" * 50)
    print("Chairman POS Server")
    print("=" * 50)
    
    # Check database connection
    conn = get_db()
    if conn:
        print("\n✓ MySQL Database: Connected")
        conn.close()
    else:
        print("\n✗ MySQL Database: Connection Failed")
        print("  Make sure MySQL is running and 'zilla' database exists")
    
    print("\nM-PESA Configuration:")
    print(f"  Environment: {MPESA_CONFIG['env']}")
    print(f"  Shortcode: {MPESA_CONFIG['shortcode']}")
    print(f"  Consumer Key: {MPESA_CONFIG['consumer_key'][:20]}...")
    print(f"  Callback: {MPESA_CONFIG['callback_url']}")
    
    print("\nAvailable Printers:")
    for p in get_printers():
        default = " (DEFAULT)" if p == win32print.GetDefaultPrinter() else ""
        print(f"  - {p}{default}")
    print("\nStarting server at http://localhost:5000")
    print("=" * 50)
    app.run(debug=True, host='0.0.0.0', port=5000)
