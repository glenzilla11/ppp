/**
 * Barcode Scanner Initialization
 */

function initializeBarcodeScanner() {
    const searchInput = document.getElementById('search');
    
    if (!searchInput) return;
    
    // Always focus on search input
    searchInput.focus();
    
    // Re-focus after any click outside modals and inputs
    document.addEventListener('click', (e) => {
        // Don't refocus if clicking on modal, input, or button
        if (!e.target.closest('input, button, .modal, .search-results')) {
            setTimeout(() => searchInput.focus(), 10);
        }
    });
    
    // Handle search input with debounce
    let searchTimeout;
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        
        if (query.length < 2) {
            document.getElementById('searchResults').innerHTML = '';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Handle Enter key (barcode scan ends with Enter)
    searchInput.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const code = searchInput.value.trim();
            
            if (code) {
                // First try exact barcode match
                const product = await getProductByBarcode(code);
                
                if (product) {
                    addProduct(product);
                    searchInput.value = '';
                    document.getElementById('searchResults').innerHTML = '';
                    playBeep();
                } else {
                    // Show "not found" message
                    alert('Product not found: ' + code);
                }
            }
        }
    });
}

/**
 * Perform product search
 */
async function performSearch(query) {
    try {
        const response = await fetch('api/search.php?q=' + encodeURIComponent(query));
        const data = await response.json();
        
        if (data.success && data.results.length > 0) {
            displaySearchResults(data.results);
        } else {
            document.getElementById('searchResults').innerHTML = '<div style="padding: 10px; color: #999;">No products found</div>';
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}

/**
 * Display search results
 */
function displaySearchResults(results) {
    const resultsDiv = document.getElementById('searchResults');
    let html = '';
    
    results.forEach(product => {
        html += `
            <div class="search-result-item" onclick="addProduct({
                id: ${product.id},
                barcode: '${product.barcode}',
                name: '${addslashes(product.product_name)}',
                price: ${product.selling_price},
                stock: ${product.stock_quantity}
            })">
                <div class="search-result-name">${product.product_name}</div>
                <div class="search-result-barcode">#${product.barcode} | KES ${formatMoney(product.selling_price)} | Stock: ${product.stock_quantity}</div>
            </div>
        `;
    });
    
    resultsDiv.innerHTML = html;
}

/**
 * Get product by barcode
 */
async function getProductByBarcode(barcode) {
    try {
        const response = await fetch('api/barcode.php?code=' + encodeURIComponent(barcode));
        const data = await response.json();
        
        if (data.success) {
            return {
                id: data.product.id,
                barcode: data.product.barcode,
                name: data.product.product_name,
                price: data.product.selling_price,
                stock: data.product.stock_quantity
            };
        }
        return null;
    } catch (error) {
        console.error('Barcode lookup error:', error);
        return null;
    }
}

/**
 * Play beep sound
 */
function playBeep() {
    // Simple beep using Web Audio API
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.1);
    } catch (e) {
        // Fallback: console log or silent fail
        console.log('Beep sound not available');
    }
}

// String escape function
function addslashes(str) {
    return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
}

// Format money
function formatMoney(amount) {
    return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Initialize scanner on page load
window.addEventListener('load', initializeBarcodeScanner);
