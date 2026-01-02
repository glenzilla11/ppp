<?php
if (!isLoggedIn()) redirect('index.php');
if (!in_array($_SESSION['user_role'], ['admin', 'manager', 'cashier'])) redirect('index.php');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/Product.php';

$product = new Product();
$products = $product->getAll($_SESSION['company_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo COMPANY_NAME; ?> - Chairman POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <div class="logo">
                <i class="fas fa-cash-register"></i>
                <span><?php echo COMPANY_NAME; ?></span>
            </div>
        </div>

        <div class="header-center">
            <div class="search-box">
                <i class="fas fa-barcode"></i>
                <input type="text" id="search" placeholder="Barcode / Product Name (Always Ready)">
                <div id="searchResults" class="search-results"></div>
            </div>
        </div>

        <div class="header-right">
            <span class="user-info">
                <i class="fas fa-user"></i>
                <?php echo $_SESSION['user_name']; ?>
            </span>
            <button class="theme-btn" id="themeToggle" onclick="toggleTheme()" title="Toggle Theme">
                <i class="fas fa-sun"></i>
            </button>
            <button class="btn btn-outline" onclick="logout()" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Products Grid (Left Side) -->
        <div class="products-section">
            <div class="products-header">
                <h3>Products</h3>
                <span class="product-count"><?php echo count($products); ?> items</span>
            </div>

            <div class="products-grid" id="productsGrid">
                <?php foreach ($products as $product): ?>
                <div class="product-card" onclick="addProduct({
                    id: <?php echo $product['id']; ?>,
                    barcode: '<?php echo $product['barcode']; ?>',
                    name: '<?php echo addslashes($product['product_name']); ?>',
                    price: <?php echo $product['selling_price']; ?>,
                    stock: <?php echo $product['stock_quantity']; ?>
                })" title="Click to add or press Enter">
                    <div class="product-name"><?php echo $product['product_name']; ?></div>
                    <div class="product-barcode">#<?php echo $product['barcode']; ?></div>
                    <div class="product-footer">
                        <span class="product-price">KES <?php echo formatMoney($product['selling_price']); ?></span>
                        <span class="product-stock"><?php echo $product['stock_quantity']; ?> <?php echo $product['unit']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Cart Section (Right Side) -->
        <div class="cart-section">
            <div class="cart-header">
                <h3>Sale Cart</h3>
                <button class="btn-small" onclick="clearCart()" title="F2"><i class="fas fa-trash"></i> Clear</button>
            </div>

            <div class="cart-items">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;">#</th>
                            <th>Item</th>
                            <th>Barcode</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Total</th>
                            <th style="width: 30px;"></th>
                        </tr>
                    </thead>
                    <tbody id="cartBody">
                        <tr class="empty-cart"><td colspan="7" style="text-align: center; color: #999;">Cart is empty</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Cart Totals -->
            <div class="cart-totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span id="subtotal" class="amount">0.00</span>
                </div>
                <div class="total-row">
                    <span>Discount:</span>
                    <input type="number" id="discount" value="0" step="0.01" onchange="updateCartTotal()" style="width: 100px; padding: 5px;">
                </div>
                <div class="total-row total">
                    <span>TOTAL:</span>
                    <span id="totalAmount" class="amount">0.00</span>
                </div>
            </div>

            <!-- Payment Buttons -->
            <div class="payment-buttons">
                <button class="btn btn-success btn-block" id="cashBtn" onclick="openCashModal()" disabled title="F5">
                    <i class="fas fa-money-bill"></i> Cash (F5)
                </button>
                <button class="btn btn-info btn-block" id="mpesaBtn" onclick="openMpesaModal()" disabled title="F6">
                    <i class="fas fa-mobile-alt"></i> M-Pesa (F6)
                </button>
            </div>
        </div>
    </main>

    <!-- Cash Payment Modal -->
    <div class="modal" id="cashModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Cash Payment</h3>
                <button class="btn-close" onclick="closeCashModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-amount">
                    <span>Amount Due:</span>
                    <strong id="cashDue" class="amount">0.00</strong>
                </div>

                <div class="form-group">
                    <label>Amount Received</label>
                    <input type="number" id="amountPaid" class="form-control" placeholder="0.00" step="0.01" oninput="calculateChange()">
                </div>

                <div class="form-group">
                    <label>Change</label>
                    <input type="text" id="changeAmount" class="form-control" readonly value="0.00">
                </div>

                <div id="cashError" class="alert alert-danger" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCashModal()">Cancel</button>
                <button class="btn btn-primary" onclick="processCashPayment()">Complete Payment (Enter)</button>
            </div>
        </div>
    </div>

    <!-- M-Pesa Modal -->
    <div class="modal" id="mpesaModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>M-Pesa Payment</h3>
                <button class="btn-close" onclick="closeMpesaModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="modal-amount">
                    <span>Amount Due:</span>
                    <strong id="mpesaDue" class="amount">0.00</strong>
                </div>

                <div id="mpesaForm" class="form-group">
                    <label>Customer Phone Number</label>
                    <input type="tel" id="mpesaPhone" class="form-control" placeholder="0712345678" pattern="^(07|01|2547|2541)\d{8}$">
                    <small>Format: 07XXXXXXXX or 254XXXXXXXX</small>
                </div>

                <div id="mpesaLoading" class="loading-state" style="display: none;">
                    <div class="spinner"></div>
                    <p>Waiting for M-Pesa confirmation...</p>
                    <small>Customer should enter M-Pesa PIN</small>
                </div>

                <div id="mpesaSuccess" class="success-state" style="display: none;">
                    <i class="fas fa-check-circle"></i>
                    <p>Payment Successful!</p>
                    <p class="mpesa-receipt">Ref: <span id="mpesaReceiptNo"></span></p>
                </div>

                <div id="mpesaError" class="alert alert-danger" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="mpesaCancelBtn" onclick="closeMpesaModal()">Cancel</button>
                <button class="btn btn-primary" id="mpesaSendBtn" onclick="sendStkPush()">Send STK Push</button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Sale Completed</h3>
            </div>
            <div class="modal-body">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4>Transaction Successful!</h4>
                <p>Sale Number: <strong id="successSaleNumber"></strong></p>
                <p>Amount: <strong class="amount" id="successAmount"></strong></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-block" onclick="closeSuccessModal()">New Sale (F2)</button>
            </div>
        </div>
    </div>

    <!-- Shortcuts Bar -->
    <footer class="shortcuts-bar">
        <span><kbd>F2</kbd> New Sale</span>
        <span><kbd>F3</kbd> Search</span>
        <span><kbd>F5</kbd> Cash</span>
        <span><kbd>F6</kbd> M-Pesa</span>
        <span><kbd>F8</kbd> Reprint</span>
        <span><kbd>ESC</kbd> Cancel</span>
    </footer>

    <!-- Print Container -->
    <div id="printContainer" style="display: none;"></div>

    <script src="assets/js/themes.js"></script>
    <script src="assets/js/barcode-scanner.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
