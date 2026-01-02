/**
 * Chairman POS - Main Application JavaScript
 */

// Global cart state
let cart = [];
let lastSaleId = null;

// Initialize on page load
window.addEventListener('load', () => {
    initializeBarcodeScanner();
    updateCartDisplay();
    document.getElementById('search').focus();
});

/**
 * Add product to cart
 */
function addProduct(product) {
    // Check stock
    if (product.stock <= 0) {
        alert('Product out of stock');
        return;
    }
    
    // Check if product already in cart
    const existingItem = cart.find(item => item.id === product.id);
    
    if (existingItem) {
        // Increase quantity
        if (existingItem.quantity < product.stock) {
            existingItem.quantity++;
        } else {
            alert('Insufficient stock');
        }
    } else {
        // Add new item to cart
        cart.push({
            id: product.id,
            barcode: product.barcode,
            name: product.name,
            price: product.price,
            quantity: 1,
            stock: product.stock
        });
    }
    
    updateCartDisplay();
    playBeep();
    document.getElementById('search').focus();
}

/**
 * Update cart display
 */
function updateCartDisplay() {
    const cartBody = document.getElementById('cartBody');
    const cashBtn = document.getElementById('cashBtn');
    const mpesaBtn = document.getElementById('mpesaBtn');
    
    if (cart.length === 0) {
        cartBody.innerHTML = '<tr class="empty-cart"><td colspan="7" style="text-align: center; color: #999;">Cart is empty</td></tr>';
        cashBtn.disabled = true;
        mpesaBtn.disabled = true;
    } else {
        let html = '';
        cart.forEach((item, index) => {
            const subtotal = item.price * item.quantity;
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.name}</td>
                    <td style="font-family: Courier New; font-size: 11px;">${item.barcode}</td>
                    <td>${formatMoney(item.price)}</td>
                    <td>
                        <button class="qty-btn" onclick="decreaseQty(${index})">−</button>
                        <input type="number" class="qty-input" value="${item.quantity}" onchange="setQty(${index}, this.value)">
                        <button class="qty-btn" onclick="increaseQty(${index})">+</button>
                    </td>
                    <td>${formatMoney(subtotal)}</td>
                    <td>
                        <button class="remove-btn" onclick="removeFromCart(${index})">×</button>
                    </td>
                </tr>
            `;
        });
        cartBody.innerHTML = html;
        cashBtn.disabled = false;
        mpesaBtn.disabled = false;
    }
    
    updateCartTotal();
}

/**
 * Update cart total
 */
function updateCartTotal() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });
    
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const total = subtotal - discount;
    
    document.getElementById('subtotal').textContent = formatMoney(subtotal);
    document.getElementById('totalAmount').textContent = formatMoney(total);
    document.getElementById('cashDue').textContent = formatMoney(total);
    document.getElementById('mpesaDue').textContent = formatMoney(total);
}

/**
 * Increase quantity
 */
function increaseQty(index) {
    if (cart[index].quantity < cart[index].stock) {
        cart[index].quantity++;
        updateCartDisplay();
    } else {
        alert('Cannot exceed available stock');
    }
}

/**
 * Decrease quantity
 */
function decreaseQty(index) {
    if (cart[index].quantity > 1) {
        cart[index].quantity--;
        updateCartDisplay();
    } else {
        removeFromCart(index);
    }
}

/**
 * Set quantity
 */
function setQty(index, value) {
    const qty = parseInt(value) || 1;
    
    if (qty < 1) {
        removeFromCart(index);
    } else if (qty > cart[index].stock) {
        alert('Cannot exceed available stock');
        cart[index].quantity = cart[index].stock;
    } else {
        cart[index].quantity = qty;
    }
    
    updateCartDisplay();
}

/**
 * Remove from cart
 */
function removeFromCart(index) {
    cart.splice(index, 1);
    updateCartDisplay();
}

/**
 * Clear cart
 */
function clearCart() {
    if (cart.length === 0) {
        alert('Cart is already empty');
        return;
    }
    
    if (confirm('Clear all items from cart?')) {
        cart = [];
        updateCartDisplay();
        document.getElementById('search').value = '';
        document.getElementById('search').focus();
    }
}

/**
 * Open cash payment modal
 */
function openCashModal() {
    if (cart.length === 0) {
        alert('Cart is empty');
        return;
    }
    
    document.getElementById('cashModal').classList.add('active');
    document.getElementById('amountPaid').value = '';
    document.getElementById('changeAmount').value = '0.00';
    document.getElementById('cashError').style.display = 'none';
    
    setTimeout(() => {
        document.getElementById('amountPaid').focus();
    }, 100);
}

/**
 * Close cash modal
 */
function closeCashModal() {
    document.getElementById('cashModal').classList.remove('active');
    document.getElementById('search').focus();
}

/**
 * Calculate change
 */
function calculateChange() {
    const total = parseFloat(document.getElementById('totalAmount').textContent.replace(/,/g, '')) || 0;
    const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
    const change = amountPaid - total;
    
    document.getElementById('changeAmount').value = formatMoney(Math.max(change, 0));
}

/**
 * Process cash payment
 */
async function processCashPayment() {
    const total = parseFloat(document.getElementById('totalAmount').textContent.replace(/,/g, '')) || 0;
    const amountPaid = parseFloat(document.getElementById('amountPaid').value) || 0;
    const errorEl = document.getElementById('cashError');
    
    if (!amountPaid || amountPaid < total) {
        errorEl.textContent = 'Amount paid must be at least KES ' + formatMoney(total);
        errorEl.style.display = 'block';
        return;
    }

    const changeAmount = amountPaid - total;
    
    // Prepare sale data with correct item structure
    const saleData = {
        subtotal: getTotalSubtotal(),
        total: total,
        amount_paid: amountPaid,
        change_amount: changeAmount,
        payment_method: 'cash',
        items: cart.map(item => ({
            id: item.id,
            quantity: item.quantity,
            price: item.price,
            subtotal: item.quantity * item.price
        }))
    };
    
    try {
        const response = await fetch('api/sale.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(saleData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            lastSaleId = data.sale_id;
            closeCashModal();
            showSuccessModal(data.data.sale_number, total);
            
            // Print receipt
            setTimeout(() => {
                printReceipt(data.receipt_html);
            }, 500);
        } else {
            errorEl.textContent = data.message || 'Sale failed';
            errorEl.style.display = 'block';
        }
    } catch (error) {
        errorEl.textContent = 'Connection error: ' + error.message;
        errorEl.style.display = 'block';
    }
}

/**
 * Open M-Pesa modal
 */
function openMpesaModal() {
    if (cart.length === 0) {
        alert('Cart is empty');
        return;
    }
    
    document.getElementById('mpesaModal').classList.add('active');
    document.getElementById('mpesaPhone').value = '';
    document.getElementById('mpesaForm').style.display = 'block';
    document.getElementById('mpesaLoading').style.display = 'none';
    document.getElementById('mpesaSuccess').style.display = 'none';
    document.getElementById('mpesaError').style.display = 'none';
    document.getElementById('mpesaCancelBtn').style.display = 'block';
    document.getElementById('mpesaSendBtn').style.display = 'block';
    
    setTimeout(() => {
        document.getElementById('mpesaPhone').focus();
    }, 100);
}

/**
 * Close M-Pesa modal
 */
function closeMpesaModal() {
    document.getElementById('mpesaModal').classList.remove('active');
    resetMpesaForm();
    document.getElementById('search').focus();
}

/**
 * Reset M-Pesa form
 */
function resetMpesaForm() {
    document.getElementById('mpesaForm').style.display = 'block';
    document.getElementById('mpesaLoading').style.display = 'none';
    document.getElementById('mpesaSuccess').style.display = 'none';
    document.getElementById('mpesaError').style.display = 'none';
}

/**
 * Send STK Push
 */
async function sendStkPush() {
    const phone = document.getElementById('mpesaPhone').value.trim();
    const errorEl = document.getElementById('mpesaError');
    
    // Validate phone
    if (!phone) {
        errorEl.textContent = 'Please enter phone number';
        errorEl.style.display = 'block';
        return;
    }
    
    if (!/^(07|01|2547|2541)\d{8}$/.test(phone)) {
        errorEl.textContent = 'Invalid phone number format';
        errorEl.style.display = 'block';
        return;
    }
    
    const total = parseFloat(document.getElementById('totalAmount').textContent.replace(/,/g, '')) || 0;
    
    // Show loading state
    document.getElementById('mpesaForm').style.display = 'none';
    document.getElementById('mpesaLoading').style.display = 'block';
    document.getElementById('mpesaError').style.display = 'none';
    document.getElementById('mpesaCancelBtn').disabled = true;
    
    try {
        const response = await fetch('api/mpesa_stk.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                phone: phone,
                amount: total
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Start polling for status
            pollMpesaStatus(data.checkout_request_id, 0);
        } else {
            document.getElementById('mpesaForm').style.display = 'block';
            document.getElementById('mpesaLoading').style.display = 'none';
            document.getElementById('mpesaCancelBtn').disabled = false;
            errorEl.textContent = data.message || 'Failed to send STK push';
            errorEl.style.display = 'block';
        }
    } catch (error) {
        document.getElementById('mpesaForm').style.display = 'block';
        document.getElementById('mpesaLoading').style.display = 'none';
        document.getElementById('mpesaCancelBtn').disabled = false;
        errorEl.textContent = 'Connection error: ' + error.message;
        errorEl.style.display = 'block';
    }
}

/**
 * Poll M-Pesa status
 */
function pollMpesaStatus(checkoutRequestId, pollCount) {
    if (pollCount > 30) {
        // Timeout after 60 seconds
        resetMpesaForm();
        document.getElementById('mpesaError').textContent = 'Payment timeout - please try again';
        document.getElementById('mpesaError').style.display = 'block';
        document.getElementById('mpesaCancelBtn').disabled = false;
        return;
    }
    
    fetch('api/mpesa_status.php?checkout_id=' + encodeURIComponent(checkoutRequestId))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.status === 'completed') {
                // Payment successful
                completeMpesaSale(data.mpesa_receipt);
            } else if (data.status === 'failed') {
                resetMpesaForm();
                document.getElementById('mpesaError').textContent = data.message || 'Payment failed';
                document.getElementById('mpesaError').style.display = 'block';
                document.getElementById('mpesaCancelBtn').disabled = false;
            } else {
                // Still pending, poll again
                setTimeout(() => {
                    pollMpesaStatus(checkoutRequestId, pollCount + 1);
                }, 2000);
            }
        })
        .catch(error => {
            // Retry on error
            setTimeout(() => {
                pollMpesaStatus(checkoutRequestId, pollCount + 1);
            }, 2000);
        });
}

/**
 * Complete M-Pesa sale
 */
async function completeMpesaSale(mpesaReceipt) {
    const total = parseFloat(document.getElementById('totalAmount').textContent.replace(/,/g, '')) || 0;
    const phone = document.getElementById('mpesaPhone').value;
    
    const saleData = {
        subtotal: getTotalSubtotal(),
        total: total,
        amount_paid: total,
        change_amount: 0,
        payment_method: 'mpesa',
        mpesa_phone: phone,
        mpesa_receipt: mpesaReceipt,
        items: cart.map(item => ({
            id: item.id,
            quantity: item.quantity,
            price: item.price,
            subtotal: item.quantity * item.price
        }))
    };
    
    try {
        const response = await fetch('api/sale.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(saleData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            lastSaleId = data.sale_id;
            
            // Show success state
            document.getElementById('mpesaLoading').style.display = 'none';
            document.getElementById('mpesaSuccess').style.display = 'block';
            document.getElementById('mpesaReceiptNo').textContent = mpesaReceipt;
            document.getElementById('mpesaCancelBtn').disabled = false;
            document.getElementById('mpesaSendBtn').disabled = true;
            
            // Print receipt after a short delay
            setTimeout(() => {
                printReceipt(data.receipt_html);
            }, 500);
            
            // Close modal and reset cart after success display
            setTimeout(() => {
                closeSuccessAndNewSale();
            }, 3000);
        } else {
            resetMpesaForm();
            document.getElementById('mpesaError').textContent = data.message || 'Failed to complete sale';
            document.getElementById('mpesaError').style.display = 'block';
            document.getElementById('mpesaCancelBtn').disabled = false;
        }
    } catch (error) {
        resetMpesaForm();
        document.getElementById('mpesaError').textContent = 'Connection error: ' + error.message;
        document.getElementById('mpesaError').style.display = 'block';
        document.getElementById('mpesaCancelBtn').disabled = false;
    }
}

/**
 * Show success modal
 */
function showSuccessModal(saleNumber, amount) {
    document.getElementById('successSaleNumber').textContent = saleNumber;
    document.getElementById('successAmount').textContent = formatMoney(amount);
    document.getElementById('successModal').classList.add('active');
}

/**
 * Close success modal
 */
function closeSuccessModal() {
    document.getElementById('successModal').classList.remove('active');
    closeSuccessAndNewSale();
}

/**
 * Close success and start new sale
 */
function closeSuccessAndNewSale() {
    document.getElementById('successModal').classList.remove('active');
    document.getElementById('mpesaModal').classList.remove('active');
    cart = [];
    updateCartDisplay();
    document.getElementById('search').value = '';
    document.getElementById('search').focus();
}

/**
 * Get total subtotal (before discount)
 */
function getTotalSubtotal() {
    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });
    return subtotal;
}

/**
 * Print receipt
 */
function printReceipt(receiptHtml) {
    const printContainer = document.getElementById('printContainer');
    printContainer.innerHTML = receiptHtml;
    window.print();
}

/**
 * Logout
 */
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('api/logout.php').then(() => {
            window.location.href = 'index.php';
        });
    }
}

/**
 * Keyboard shortcuts
 */
document.addEventListener('keydown', (e) => {
    // Don't trigger shortcuts while typing in search or amount fields
    if (e.target.tagName === 'INPUT' && e.key !== 'Escape' && e.key !== 'Enter' && !e.key.startsWith('F')) {
        return;
    }
    
    switch(e.key) {
        case 'F2':
            e.preventDefault();
            clearCart();
            break;
        case 'F3':
            e.preventDefault();
            document.getElementById('search').focus();
            break;
        case 'F5':
            e.preventDefault();
            if (cart.length > 0) openCashModal();
            break;
        case 'F6':
            e.preventDefault();
            if (cart.length > 0) openMpesaModal();
            break;
        case 'F8':
            e.preventDefault();
            if (lastSaleId) reprintLastReceipt();
            else alert('No sale to reprint');
            break;
        case 'Escape':
            e.preventDefault();
            document.getElementById('cashModal').classList.remove('active');
            document.getElementById('mpesaModal').classList.remove('active');
            document.getElementById('successModal').classList.remove('active');
            document.getElementById('search').focus();
            break;
        case 'Enter':
            const cashModal = document.getElementById('cashModal');
            if (cashModal.classList.contains('active')) {
                e.preventDefault();
                processCashPayment();
            }
            break;
    }
});

/**
 * Reprint last receipt
 */
function reprintLastReceipt() {
    if (lastSaleId) {
        fetch('api/receipt.php?sale_id=' + lastSaleId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    printReceipt(data.html);
                }
            })
            .catch(error => alert('Error: ' + error.message));
    }
}

/**
 * Format money utility
 */
function formatMoney(amount) {
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
