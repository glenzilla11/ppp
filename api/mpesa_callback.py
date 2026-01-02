from flask import request, jsonify
from datetime import datetime
import pymysql
import json

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

def handle_mpesa_callback(app):
    """Register M-PESA callback endpoint"""
    
    @app.route('/api/mpesa_callback', methods=['POST'])
    def mpesa_callback():
        print("\n" + "=" * 50)
        print("📲 M-PESA CALLBACK RECEIVED")
        print("=" * 50)
        
        try:
            # Get callback data
            callback_data = request.json
            print(f"Callback data: {json.dumps(callback_data, indent=2)}")
            
            # Extract the STK callback
            stk_callback = callback_data.get('Body', {}).get('stkCallback', {})
            
            merchant_request_id = stk_callback.get('MerchantRequestID')
            checkout_request_id = stk_callback.get('CheckoutRequestID')
            result_code = stk_callback.get('ResultCode')
            result_desc = stk_callback.get('ResultDesc')
            
            print(f"   MerchantRequestID: {merchant_request_id}")
            print(f"   CheckoutRequestID: {checkout_request_id}")
            print(f"   ResultCode: {result_code}")
            print(f"   ResultDesc: {result_desc}")
            
            conn = get_db()
            if not conn:
                print("❌ Database connection failed")
                return jsonify({'ResultCode': 0, 'ResultDesc': 'Accepted'})
            
            cursor = conn.cursor(pymysql.cursors.DictCursor)
            
            try:
                # Find the transaction
                cursor.execute("""
                    SELECT * FROM mpesa_transactions 
                    WHERE checkout_request_id = %s
                """, (checkout_request_id,))
                
                transaction = cursor.fetchone()
                
                if not transaction:
                    print(f"❌ Transaction not found for checkout_request_id: {checkout_request_id}")
                    return jsonify({'ResultCode': 0, 'ResultDesc': 'Accepted'})
                
                sale_id = transaction['sale_id']
                
                if result_code == 0:
                    # Payment successful
                    print("✓ Payment successful!")
                    
                    # Extract callback metadata
                    callback_metadata = stk_callback.get('CallbackMetadata', {}).get('Item', [])
                    
                    mpesa_receipt_number = None
                    transaction_date = None
                    phone_number = None
                    amount = None
                    
                    for item in callback_metadata:
                        name = item.get('Name')
                        value = item.get('Value')
                        
                        if name == 'MpesaReceiptNumber':
                            mpesa_receipt_number = value
                        elif name == 'TransactionDate':
                            transaction_date = value
                        elif name == 'PhoneNumber':
                            phone_number = value
                        elif name == 'Amount':
                            amount = value
                    
                    print(f"   M-PESA Receipt: {mpesa_receipt_number}")
                    print(f"   Amount: {amount}")
                    print(f"   Phone: {phone_number}")
                    
                    # Update transaction
                    cursor.execute("""
                        UPDATE mpesa_transactions 
                        SET status = 'completed',
                            mpesa_receipt_number = %s,
                            result_code = %s,
                            result_desc = %s,
                            updated_at = NOW()
                        WHERE id = %s
                    """, (mpesa_receipt_number, result_code, result_desc, transaction['id']))
                    
                    # Update sale
                    cursor.execute("""
                        UPDATE sales 
                        SET transaction_status = 'completed',
                            paid_amount = %s,
                            mpesa_receipt_number = %s,
                            updated_at = NOW()
                        WHERE id = %s
                    """, (amount, mpesa_receipt_number, sale_id))
                    
                    conn.commit()
                    print(f"✓ Transaction and sale updated successfully")
                    
                else:
                    # Payment failed
                    print(f"❌ Payment failed: {result_desc}")
                    
                    # Update transaction
                    cursor.execute("""
                        UPDATE mpesa_transactions 
                        SET status = 'failed',
                            result_code = %s,
                            result_desc = %s,
                            updated_at = NOW()
                        WHERE id = %s
                    """, (result_code, result_desc, transaction['id']))
                    
                    # Update sale
                    cursor.execute("""
                        UPDATE sales 
                        SET transaction_status = 'failed',
                            updated_at = NOW()
                        WHERE id = %s
                    """, (sale_id,))
                    
                    conn.commit()
                    print(f"✓ Transaction marked as failed")
                
            except pymysql.Error as e:
                print(f"❌ Database error: {e}")
                conn.rollback()
            finally:
                cursor.close()
                conn.close()
            
            # Always return success to M-PESA
            return jsonify({'ResultCode': 0, 'ResultDesc': 'Accepted'})
            
        except Exception as e:
            print(f"❌ Callback error: {str(e)}")
            import traceback
            traceback.print_exc()
            return jsonify({'ResultCode': 0, 'ResultDesc': 'Accepted'})