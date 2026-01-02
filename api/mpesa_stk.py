from flask import request, jsonify
from datetime import datetime
import pymysql
import pymysql.cursors
import requests
import base64
import urllib3

# Disable SSL warnings
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

# ================================================================
# M-PESA CONFIGURATION
# ================================================================
MPESA_CONFIG = {
    'env': 'sandbox',
    'consumer_key': 'NKAYPzJZvpaJhAmuyi1x5UGOGwuHEyRxACAO39BUnzBGSq99',
    'consumer_secret': 'RzkZrjEZfHksHnxhqlfmyP4oESsqV9fXA6OtjNeiWtmlXmZrAYF5rAb5R6mZxAwr',
    'shortcode': '174379',
    'passkey': 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
    'callback_url': 'http://localhost:5000/api/mpesa_callback'
}

if MPESA_CONFIG['env'] == 'sandbox':
    MPESA_CONFIG['auth_url'] = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
    MPESA_CONFIG['stk_url'] = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
else:
    MPESA_CONFIG['auth_url'] = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
    MPESA_CONFIG['stk_url'] = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'

# ================================================================
# DATABASE CONFIGURATION
# ================================================================
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'passwd': '',
    'db': 'zilla',
    'charset': 'utf8mb4'
}

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

def get_mpesa_access_token():
    """Get M-PESA access token"""
    try:
        auth_string = base64.b64encode(
            f"{MPESA_CONFIG['consumer_key']}:{MPESA_CONFIG['consumer_secret']}".encode()
        ).decode()
        
        headers = {
            'Authorization': f'Basic {auth_string}',
            'Content-Type': 'application/json'
        }
        
        print(f"\n🔐 Requesting M-PESA Access Token...")
        
        response = requests.get(
            MPESA_CONFIG['auth_url'], 
            headers=headers, 
            timeout=30,
            verify=False
        )
        
        print(f"   HTTP Status: {response.status_code}")
        
        if response.status_code != 200:
            print(f"   ✗ Error Response: {response.text}")
            return None
        
        data = response.json()
        
        if 'access_token' not in data:
            print(f"   ✗ No access_token in response: {data}")
            return None
        
        token = data['access_token']
        print(f"✓ Token obtained")
        return token
        
    except Exception as e:
        print(f"✗ M-PESA Auth Error: {e}")
        return None

def generate_mpesa_password(timestamp):
    """Generate M-PESA password for STK push
    
    IMPORTANT: Just base64 encode - NO hashing!
    """
    data = f"{MPESA_CONFIG['shortcode']}{MPESA_CONFIG['passkey']}{timestamp}"
    return base64.b64encode(data.encode()).decode()

def format_phone_number(phone):
    """Format phone number to 254XXXXXXXXX format"""
    phone = str(phone).strip()
    
    # Remove any spaces, dashes, or other characters
    phone = ''.join(filter(str.isdigit, phone))
    
    # Handle different formats
    if phone.startswith('0'):
        phone = '254' + phone[1:]
    elif phone.startswith('+254'):
        phone = phone[1:]  # Remove the +
    elif phone.startswith('254'):
        pass  # Already correct format
    elif phone.startswith('7') or phone.startswith('1'):
        phone = '254' + phone
    
    return phone

def handle_mpesa_stk(app):
    """Register M-PESA STK push endpoint"""
    
    @app.route('/api/mpesa_stk', methods=['POST'])
    def mpesa_stk():
        print("\n" + "=" * 50)
        print("📱 API ENDPOINT: /api/mpesa_stk")
        print("=" * 50)
        
        try:
            data = request.json
            print(f"\n📥 Request data: {data}")
            
            phone_number = data.get('phone_number')
            amount = data.get('amount')
            cart = data.get('cart', [])
            cashier_id = data.get('cashier_id', 1)
            
            # Validate inputs
            if not phone_number:
                return jsonify({'success': False, 'error': 'Phone number is required'}), 400
            
            if not amount or float(amount) < 1:
                return jsonify({'success': False, 'error': 'Amount must be at least KES 1'}), 400
            
            # Format phone number
            phone_number = format_phone_number(phone_number)
            amount = int(float(amount))
            
            print(f"   Phone: {phone_number}")
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
                # Generate receipt number
                receipt_number = f"RCP{datetime.now().strftime('%Y%m%d%H%M%S')}"
                
                # Insert sale with pending status
                cursor.execute("""
                    INSERT INTO sales 
                    (receipt_number, cashier_id, subtotal, total, paid_amount, change_amount, 
                     payment_method, transaction_status)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """, (receipt_number, cashier_id, amount, amount, 0, 0, 'mpesa', 'pending'))
                
                sale_id = cursor.lastrowid
                
                # Insert sale items
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
            
            # Now initiate STK push
            token = get_mpesa_access_token()
            if not token:
                # Mark sale as failed
                mark_sale_failed(sale_id)
                return jsonify({'success': False, 'error': 'Failed to get M-PESA access token'}), 500
            
            timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
            password = generate_mpesa_password(timestamp)
            
            payload = {
                "BusinessShortCode": MPESA_CONFIG['shortcode'],
                "Password": password,
                "Timestamp": timestamp,
                "TransactionType": "CustomerPayBillOnline",
                "Amount": amount,
                "PartyA": phone_number,
                "PartyB": MPESA_CONFIG['shortcode'],
                "PhoneNumber": phone_number,
                "CallBackURL": MPESA_CONFIG['callback_url'],
                "AccountReference": f"ChairmanPOS_{sale_id}",
                "TransactionDesc": f"Payment for sale {sale_id}"
            }
            
            headers = {
                'Authorization': f'Bearer {token}',
                'Content-Type': 'application/json'
            }
            
            print(f"\n💳 Sending STK Push...")
            print(f"   Phone: {phone_number}")
            print(f"   Amount: {amount}")
            
            response = requests.post(
                MPESA_CONFIG['stk_url'],
                json=payload,
                headers=headers,
                timeout=30,
                verify=False
            )
            
            print(f"   HTTP Status: {response.status_code}")
            print(f"   Response: {response.text}")
            
            if response.status_code != 200:
                mark_sale_failed(sale_id)
                print(f"   Sale marked as failed")
                print(f"❌ STK Push failed: HTTP {response.status_code}: {response.text}")
                return jsonify({
                    'success': False, 
                    'error': f'M-PESA error: {response.text}'
                }), 400
            
            data = response.json()
            
            # Check for success
            if data.get('ResponseCode') == '0' or data.get('ResponseCode') == 0:
                # Save M-PESA transaction
                conn = get_db()
                if conn:
                    cursor = conn.cursor()
                    try:
                        cursor.execute("""
                            INSERT INTO mpesa_transactions 
                            (sale_id, merchant_request_id, checkout_request_id, phone_number, amount, status)
                            VALUES (%s, %s, %s, %s, %s, %s)
                        """, (
                            sale_id,
                            data.get('MerchantRequestID'),
                            data.get('CheckoutRequestID'),
                            phone_number,
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
                
                print(f"✓ STK push initiated successfully!")
                
                return jsonify({
                    'success': True,
                    'message': 'STK push sent to your phone',
                    'sale_id': sale_id,
                    'checkout_request_id': data.get('CheckoutRequestID'),
                    'merchant_request_id': data.get('MerchantRequestID')
                })
            else:
                error_msg = data.get('ResponseDescription') or data.get('errorMessage') or 'STK push failed'
                mark_sale_failed(sale_id)
                print(f"❌ M-PESA STK Error: {error_msg}")
                return jsonify({'success': False, 'error': error_msg}), 400
                
        except Exception as e:
            print(f"❌ Exception: {str(e)}")
            import traceback
            traceback.print_exc()
            return jsonify({'success': False, 'error': str(e)}), 500

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