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
}

if MPESA_CONFIG['env'] == 'sandbox':
    MPESA_CONFIG['auth_url'] = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
    MPESA_CONFIG['query_url'] = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
else:
    MPESA_CONFIG['auth_url'] = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
    MPESA_CONFIG['query_url'] = 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query'

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
        
        response = requests.get(
            MPESA_CONFIG['auth_url'], 
            headers=headers, 
            timeout=30,
            verify=False
        )
        
        if response.status_code != 200:
            return None
        
        data = response.json()
        return data.get('access_token')
        
    except Exception as e:
        print(f"M-PESA Auth Error: {e}")
        return None

def generate_mpesa_password(timestamp):
    """Generate M-PESA password - just base64, no hashing"""
    data = f"{MPESA_CONFIG['shortcode']}{MPESA_CONFIG['passkey']}{timestamp}"
    return base64.b64encode(data.encode()).decode()

def handle_mpesa_status(app):
    """Register M-PESA status check endpoints"""
    
    @app.route('/api/mpesa_status/<int:sale_id>', methods=['GET'])
    def check_mpesa_status(sale_id):
        """Check M-PESA payment status for a sale"""
        print(f"\n🔍 Checking M-PESA status for sale {sale_id}")
        
        try:
            conn = get_db()
            if not conn:
                return jsonify({'success': False, 'error': 'Database connection failed'}), 500
            
            cursor = conn.cursor(pymysql.cursors.DictCursor)
            
            try:
                # Get the transaction
                cursor.execute("""
                    SELECT mt.*, s.transaction_status as sale_status
                    FROM mpesa_transactions mt
                    JOIN sales s ON mt.sale_id = s.id
                    WHERE mt.sale_id = %s
                    ORDER BY mt.id DESC
                    LIMIT 1
                """, (sale_id,))
                
                transaction = cursor.fetchone()
                
                if not transaction:
                    return jsonify({
                        'success': False, 
                        'error': 'Transaction not found'
                    }), 404
                
                # Convert datetime objects
                if transaction.get('created_at'):
                    transaction['created_at'] = transaction['created_at'].isoformat()
                if transaction.get('updated_at'):
                    transaction['updated_at'] = transaction['updated_at'].isoformat()
                
                return jsonify({
                    'success': True,
                    'status': transaction['status'],
                    'sale_status': transaction['sale_status'],
                    'transaction': transaction
                })
                
            finally:
                cursor.close()
                conn.close()
                
        except Exception as e:
            print(f"Error checking status: {e}")
            return jsonify({'success': False, 'error': str(e)}), 500
    
    @app.route('/api/mpesa_query', methods=['POST'])
    def query_mpesa():
        """Query M-PESA for transaction status (calls Safaricom API)"""
        print("\n🔍 Querying M-PESA transaction status...")
        
        try:
            data = request.json
            checkout_request_id = data.get('checkout_request_id')
            
            if not checkout_request_id:
                return jsonify({'success': False, 'error': 'checkout_request_id is required'}), 400
            
            # Get access token
            token = get_mpesa_access_token()
            if not token:
                return jsonify({'success': False, 'error': 'Failed to get access token'}), 500
            
            timestamp = datetime.now().strftime('%Y%m%d%H%M%S')
            password = generate_mpesa_password(timestamp)
            
            payload = {
                "BusinessShortCode": MPESA_CONFIG['shortcode'],
                "Password": password,
                "Timestamp": timestamp,
                "CheckoutRequestID": checkout_request_id
            }
            
            headers = {
                'Authorization': f'Bearer {token}',
                'Content-Type': 'application/json'
            }
            
            response = requests.post(
                MPESA_CONFIG['query_url'],
                json=payload,
                headers=headers,
                timeout=30,
                verify=False
            )
            
            print(f"   HTTP Status: {response.status_code}")
            print(f"   Response: {response.text}")
            
            if response.status_code != 200:
                return jsonify({
                    'success': False, 
                    'error': f'M-PESA query failed: {response.text}'
                }), 400
            
            result = response.json()
            
            return jsonify({
                'success': True,
                'result': result
            })
            
        except Exception as e:
            print(f"Error querying M-PESA: {e}")
            return jsonify({'success': False, 'error': str(e)}), 500