// API base URL
const API_BASE = 'http://localhost/animates/api/';

// Sample data for RFID tags and pets (will be replaced with API calls)
let petData = {
    'A1B2C3D4': {
        petName: 'Buddy',
        breed: 'Golden Retriever',
        owner: 'John Cruz',
        phone: '+63 912 345 6789',
        checkinTime: '9:00 AM',
        bathTime: '9:30 AM',
        groomingTime: '10:30 AM',
        staff: 'Maria Santos',
        services: [
            { name: 'Basic Bath', basePrice: 300, modifier: 'Large (+50%)', amount: 450 },
            { name: 'Full Grooming', basePrice: 500, modifier: 'Large (+50%)', amount: 750 },
            { name: 'Nail Trimming', basePrice: 100, modifier: 'Standard', amount: 100 }
        ]
    },
    'B2C3D4E5': {
        petName: 'Whiskers',
        breed: 'Persian Cat',
        owner: 'Ana Lopez',
        phone: '+63 917 234 5678',
        checkinTime: '10:00 AM',
        bathTime: '10:15 AM',
        groomingTime: '10:45 AM',
        staff: 'James Rodriguez',
        services: [
            { name: 'Basic Bath', basePrice: 300, modifier: 'Small (-20%)', amount: 240 },
            { name: 'Ear Cleaning', basePrice: 150, modifier: 'Standard', amount: 150 },
            { name: 'Nail Polish', basePrice: 200, modifier: 'Standard', amount: 200 }
        ]
    },
    'D4E5F6G7': {
        petName: 'Luna',
        breed: 'Shih Tzu',
        owner: 'Maria Santos',
        phone: '+63 905 123 4567',
        checkinTime: '11:00 AM',
        bathTime: '11:20 AM',
        groomingTime: '12:00 PM',
        staff: 'Sarah Johnson',
        services: [
            { name: 'Premium Grooming', basePrice: 600, modifier: 'Medium (+20%)', amount: 720 },
            { name: 'De-shedding Treatment', basePrice: 250, modifier: 'Standard', amount: 250 }
        ]
    }
};

// Current user data
let currentUser = null;

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
    await checkAuth();
    await loadStats();
    await loadPendingBills();
    await loadPaymentProcessing();
    
    // Initialize with RFID billing section
    showSection('rfid-billing');
    
    // Set up payment method change handler
    document.getElementById('paymentMethod').addEventListener('change', handlePaymentMethodChange);
    
    // Simulate RFID scanning every 10 seconds (for demo)
    setInterval(() => {
        if (Math.random() > 0.9) { // 10% chance every 10 seconds
            simulateRfidScan();
        }
    }, 10000);
});

// Authentication check
async function checkAuth() {
    const token = localStorage.getItem('auth_token');
    const role = localStorage.getItem('auth_role');
    const staffRole = localStorage.getItem('auth_staff_role');
    
    if (!token || !role) {
        redirectToAuth();
        return false;
    }
    
    // Check if user has access to billing (admin or cashier)
    if (role !== 'admin' && staffRole !== 'cashier') {
        alert('Access denied. Only admin and cashier staff can access billing management.');
        redirectToAuth();
        return false;
    }

    // Set current user from localStorage
    currentUser = {
        id: localStorage.getItem('auth_user_id') || 'unknown',
        email: localStorage.getItem('auth_email'),
        role: role,
        staff_role: staffRole
    };
    
    updateUserInfo();
    return true;
}

// Update user information display
function updateUserInfo() {
    if (currentUser) {
        const userName = currentUser.email.split('@')[0];
        const userInitial = userName.charAt(0).toUpperCase();
        
        document.getElementById('userName').textContent = userName;
        document.getElementById('userRole').textContent = currentUser.staff_role === 'cashier' ? 'Cashier' : 'Admin';
        document.getElementById('userInitials').textContent = userInitial;
    }
}

// Redirect to auth page
function redirectToAuth() {
    localStorage.clear();
    window.location.replace('admin_staff_auth.html');
}

// Load statistics
async function loadStats() {
    try {
        // In a real system, this would fetch from API
        document.getElementById('todayRevenue').textContent = '₱8,450';
        document.getElementById('pendingBills').textContent = '7';
        document.getElementById('processingBills').textContent = '3';
        document.getElementById('weekRevenue').textContent = '₱42,350';
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Load pending bills
async function loadPendingBills() {
    try {
        const container = document.getElementById('pendingBillsContainer');
        
        // Sample pending bills data
        const pendingBills = [
            {
                id: 'B2C3D4E5',
                petName: 'Buddy',
                service: 'Full Grooming',
                owner: 'John Cruz',
                rfid: 'B2C3D4E5',
                services: 'Full Grooming Package, Nail Trim, De-shedding',
                completed: '11:30 AM',
                duration: '1h 45m',
                amount: 1150,
                status: 'Grooming Complete'
            },
            {
                id: 'D4E5F6G7',
                petName: 'Whiskers',
                service: 'Basic Bath',
                owner: 'Ana Lopez',
                rfid: 'D4E5F6G7',
                services: 'Basic Bath, Ear Cleaning, Nail Polish',
                completed: '10:45 AM',
                duration: '45m',
                amount: 600,
                status: 'Ready for Pickup'
            },
            {
                id: 'E5F6G7H8',
                petName: 'Rocky',
                service: 'Dental Care',
                owner: 'Mike Torres',
                rfid: 'E5F6G7H8',
                services: 'Basic Bath, Dental Care, Nail Trimming',
                started: '11:00 AM',
                estCompletion: '12:30 PM',
                amount: 700,
                status: 'In Progress'
            }
        ];
        
        container.innerHTML = pendingBills.map(bill => `
            <div class="border border-${getStatusColor(bill.status).border} bg-${getStatusColor(bill.status).bg} rounded-xl p-6">
                <div class="flex items-start justify-between">
                    <div class="flex items-start space-x-4">
                        <div class="w-12 h-12 bg-${getStatusColor(bill.status).icon} rounded-full flex items-center justify-center text-${getStatusColor(bill.status).text} text-xl">
                            ${bill.status === 'In Progress' ? '🐕' : bill.service.includes('Cat') ? '🐱' : '🐕'}
                        </div>
                        <div>
                            <div class="flex items-center space-x-2 mb-2">
                                <h3 class="font-semibold text-gray-900">${bill.petName} - ${bill.service}</h3>
                                <span class="px-2 py-1 bg-${getStatusColor(bill.status).badge} text-${getStatusColor(bill.status).badgeText} rounded text-xs font-medium">${bill.status}</span>
                            </div>
                            <p class="text-sm text-gray-600 mb-1">Owner: ${bill.owner} • RFID: ${bill.rfid}</p>
                            <p class="text-sm text-gray-600">Services: ${bill.services}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                ${bill.status === 'In Progress' ? 
                                    `Started: ${bill.started} • Est. completion: ${bill.estCompletion}` : 
                                    `Completed: ${bill.completed} • Duration: ${bill.duration}`}
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold text-gray-900">₱${bill.amount.toLocaleString()}</p>
                        <div class="flex space-x-2 mt-2">
                            ${bill.status === 'In Progress' ? 
                                `<button disabled class="bg-gray-300 text-gray-500 px-3 py-1 rounded text-sm font-medium cursor-not-allowed">In Progress</button>` :
                                `<button onclick="generateBillFromPending('${bill.rfid}')" class="bg-gold-500 hover:bg-gold-600 text-white px-3 py-1 rounded text-sm font-medium transition-colors">Generate Bill</button>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
    } catch (error) {
        console.error('Error loading pending bills:', error);
    }
}

// Load payment processing data
async function loadPaymentProcessing() {
    try {
        const container = document.getElementById('paymentProcessingContainer');
        const dailySummary = document.getElementById('dailySummary');
        const paymentMethodsChart = document.getElementById('paymentMethodsChart');
        
        // Sample payment processing data
        const payments = [
            {
                status: 'Payment Successful',
                time: '2 minutes ago',
                pet: 'Luna (Persian Cat)',
                owner: 'Maria Santos',
                method: 'GCash',
                receipt: '#GC20250810001',
                amount: 600,
                type: 'success'
            },
            {
                status: 'Processing Payment',
                time: 'Processing...',
                pet: 'Buddy (Golden Retriever)',
                owner: 'John Cruz',
                method: 'Credit Card',
                card: '**** **** **** 1234',
                amount: 1150,
                type: 'processing'
            }
        ];
        
        container.innerHTML = payments.map(payment => `
            <div class="border border-${getPaymentColor(payment.type).border} bg-${getPaymentColor(payment.type).bg} rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-${getPaymentColor(payment.type).text}">${payment.status}</h3>
                    <span class="text-xs text-${getPaymentColor(payment.type).timeText}">${payment.time}</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-${getPaymentColor(payment.type).content}"><strong>Pet:</strong> ${payment.pet}</p>
                        <p class="text-sm text-${getPaymentColor(payment.type).content}"><strong>Owner:</strong> ${payment.owner}</p>
                    </div>
                    <div>
                        <p class="text-sm text-${getPaymentColor(payment.type).content}"><strong>Method:</strong> ${payment.method}</p>
                        <p class="text-sm text-${getPaymentColor(payment.type).content}">
                            <strong>${payment.receipt ? 'Receipt:' : 'Card:'}</strong> ${payment.receipt || payment.card}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xl font-bold text-${getPaymentColor(payment.type).amount}">₱${payment.amount.toLocaleString()}</p>
                        ${payment.type === 'processing' ? 
                            `<button class="mt-1 text-${getPaymentColor(payment.type).button} hover:text-${getPaymentColor(payment.type).buttonHover} text-sm underline">Cancel</button>` :
                            `<button class="mt-1 text-${getPaymentColor(payment.type).button} hover:text-${getPaymentColor(payment.type).buttonHover} text-sm underline">View Receipt</button>`
                        }
                    </div>
                </div>
            </div>
        `).join('');
        
        // Daily summary
        dailySummary.innerHTML = `
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Cash Payments:</span>
                <span class="font-medium">₱3,200</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Card Payments:</span>
                <span class="font-medium">₱2,800</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-gray-600">Online Payments:</span>
                <span class="font-medium">₱2,450</span>
            </div>
            <hr class="my-2 border-gray-300">
            <div class="flex justify-between text-lg font-bold">
                <span>Total:</span>
                <span class="text-gold-600">₱8,450</span>
            </div>
        `;
        
        // Payment methods chart
        paymentMethodsChart.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-green-500 rounded"></div>
                    <span class="text-sm text-gray-600">Cash</span>
                </div>
                <span class="font-medium">38%</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-blue-500 rounded"></div>
                    <span class="text-sm text-gray-600">Cards</span>
                </div>
                <span class="font-medium">33%</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div class="w-4 h-4 bg-purple-500 rounded"></div>
                    <span class="text-sm text-gray-600">Online</span>
                </div>
                <span class="font-medium">29%</span>
            </div>
        `;
        
    } catch (error) {
        console.error('Error loading payment processing:', error);
    }
}

// Get status colors for pending bills
function getStatusColor(status) {
    switch (status) {
        case 'Grooming Complete':
            return { border: 'orange-200', bg: 'orange-50', icon: 'orange-100', text: 'orange-600', badge: 'orange-100', badgeText: 'orange-800' };
        case 'Ready for Pickup':
            return { border: 'green-200', bg: 'green-50', icon: 'green-100', text: 'green-600', badge: 'green-100', badgeText: 'green-800' };
        case 'In Progress':
            return { border: 'yellow-200', bg: 'yellow-50', icon: 'yellow-100', text: 'yellow-600', badge: 'yellow-100', badgeText: 'yellow-800' };
        default:
            return { border: 'gray-200', bg: 'gray-50', icon: 'gray-100', text: 'gray-600', badge: 'gray-100', badgeText: 'gray-800' };
    }
}

// Get payment colors
function getPaymentColor(type) {
    switch (type) {
        case 'success':
            return { border: 'green-200', bg: 'green-50', text: 'green-800', timeText: 'green-600', content: 'green-700', amount: 'green-800', button: 'green-600', buttonHover: 'green-700' };
        case 'processing':
            return { border: 'blue-200', bg: 'blue-50', text: 'blue-800', timeText: 'blue-600', content: 'blue-700', amount: 'blue-800', button: 'blue-600', buttonHover: 'blue-700' };
        default:
            return { border: 'gray-200', bg: 'gray-50', text: 'gray-800', timeText: 'gray-600', content: 'gray-700', amount: 'gray-800', button: 'gray-600', buttonHover: 'gray-700' };
    }
}

// Show section
function showSection(sectionName) {
    document.querySelectorAll('.section').forEach(section => {
        section.classList.add('hidden');
    });
    
    const targetSection = document.getElementById(sectionName);
    if (targetSection) {
        targetSection.classList.remove('hidden');
    }
}

// Simulate RFID scan
function simulateRfidScan() {
    const tags = Object.keys(petData);
    const randomTag = tags[Math.floor(Math.random() * tags.length)];
    
    document.getElementById('scannedTag').textContent = randomTag;
    document.getElementById('scannerStatus').textContent = 'Tag Detected!';
    document.getElementById('scannerMessage').textContent = 'Processing...';
    
    setTimeout(() => {
        processBilling(randomTag);
    }, 1500);
}

// Process billing
function processBilling(tagId = null) {
    const rfidTag = tagId || document.getElementById('manualRfidInput').value || document.getElementById('scannedTag').textContent;
    
    if (!rfidTag || rfidTag === '---') {
        alert('Please enter or scan an RFID tag first.');
        return;
    }
    
    // Show loading state
    document.getElementById('scannerStatus').textContent = 'Processing...';
    document.getElementById('scannerMessage').textContent = 'Fetching billing information...';
    
    // Fetch billing info from bookings table using RFID
    fetch(`${API_BASE}billing.php?rfid=${rfidTag}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data); // Debug log
            
            if (data.success && data.pet) {
                displayBillingInfo(data.pet, rfidTag);
            } else {
                // Show error message from API
                const errorMsg = data.error || 'RFID tag not found in system. Please check the tag ID.';
                alert(errorMsg);
                document.getElementById('scannerStatus').textContent = 'Tag Not Found';
                document.getElementById('scannerMessage').textContent = errorMsg;
            }
        })
        .catch(error => {
            console.error('Error fetching billing info:', error);
            const errorMsg = 'Failed to fetch billing information. Please check your connection and try again.';
            alert(errorMsg);
            document.getElementById('scannerStatus').textContent = 'Error';
            document.getElementById('scannerMessage').textContent = errorMsg;
        });
}
    
// Display billing information
function displayBillingInfo(pet, rfidTag) {
    // Update scanner status
    document.getElementById('scannerStatus').textContent = 'Bill Generated!';
    document.getElementById('scannerMessage').textContent = 'Billing information loaded successfully';
    
    // Populate pet information
    document.getElementById('billPetName').textContent = pet.petName;
    document.getElementById('billPetBreed').textContent = pet.breed;
    document.getElementById('billOwnerName').textContent = pet.owner;
    document.getElementById('billOwnerPhone').textContent = pet.phone;
    document.getElementById('billRfidTag').textContent = rfidTag;
    document.getElementById('billCheckinTime').textContent = pet.checkinTime;
    document.getElementById('billBathTime').textContent = pet.bathTime;
    document.getElementById('billGroomingTime').textContent = pet.groomingTime;
    document.getElementById('billStaff').textContent = pet.staff;
    
    // Display payment status if available
    if (pet.paymentStatus) {
        const statusElement = document.getElementById('billPaymentStatus');
        const statusClass = getPaymentStatusClass(pet.paymentStatus);
        statusElement.innerHTML = `<span class="${statusClass}">${pet.paymentStatus.toUpperCase()}</span>`;
    } else {
        document.getElementById('billPaymentStatus').textContent = 'PENDING';
    }
    
    // Use duration from API if available, otherwise calculate
    if (pet.duration) {
        document.getElementById('billDuration').textContent = pet.duration;
    } else {
        // Calculate duration from times
        const checkin = new Date(`2024-01-01 ${pet.checkinTime}`);
        const grooming = new Date(`2024-01-01 ${pet.groomingTime}`);
        const duration = Math.round((grooming - checkin) / (1000 * 60));
        document.getElementById('billDuration').textContent = `${Math.floor(duration / 60)}h ${duration % 60}m`;
    }
    
    // Populate services
    const serviceTable = document.getElementById('serviceBreakdown');
    serviceTable.innerHTML = '';
    
    let subtotal = 0;
    pet.services.forEach(service => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 text-sm text-gray-900">${service.name}</td>
            <td class="px-6 py-4 text-sm text-gray-600 text-right">₱${service.basePrice}</td>
            <td class="px-6 py-4 text-sm text-gray-600 text-center">${service.modifier}</td>
            <td class="px-6 py-4 text-sm font-medium text-gray-900 text-right">₱${service.amount}</td>
        `;
        serviceTable.appendChild(row);
        subtotal += service.amount;
    });
    
    // Update billing totals
    updateBillTotal(subtotal);
    
    // Set the current booking ID for payment processing
    if (pet.bookingId) {
        document.getElementById('currentBookingId').value = pet.bookingId;
    }
    
    // Show bill generation area
    document.getElementById('billGeneration').classList.remove('hidden');
    
    // Scroll to bill
    document.getElementById('billGeneration').scrollIntoView({ behavior: 'smooth' });
}

// Update bill total
function updateBillTotal(baseSubtotal = null) {
    let subtotal = baseSubtotal;
    if (subtotal === null) {
        subtotal = 0;
        const serviceRows = document.querySelectorAll('#serviceBreakdown tr');
        serviceRows.forEach(row => {
            const amountCell = row.cells[3];
            if (amountCell) {
                const amount = parseInt(amountCell.textContent.replace('₱', '').replace(',', ''));
                subtotal += amount;
            }
        });
    }
    
    // Calculate discount
    let discount = 0;
    const discountSelect = document.getElementById('discountSelect').value;
    const customDiscount = parseInt(document.getElementById('customDiscount').value || '0');
    
    if (discountSelect === 'custom') {
        discount = customDiscount;
    } else {
        const discountPercent = parseInt(discountSelect || '0');
        discount = Math.round(subtotal * discountPercent / 100);
    }
    
    const discountedSubtotal = subtotal - discount;
    const tax = Math.round(discountedSubtotal * 0.12);
    const total = discountedSubtotal + tax;
    
    // Update display
    document.getElementById('billSubtotal').textContent = `₱${subtotal.toLocaleString()}`;
    document.getElementById('billDiscount').textContent = `₱${discount.toLocaleString()}`;
    document.getElementById('billTax').textContent = `₱${tax.toLocaleString()}`;
    document.getElementById('billTotal').textContent = `₱${total.toLocaleString()}`;
}

// Handle payment method change
function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const onlinePaymentRef = document.getElementById('onlinePaymentRef');
    
    if (paymentMethod === 'online') {
        onlinePaymentRef.classList.remove('hidden');
    } else {
        onlinePaymentRef.classList.add('hidden');
    }
}

// Get payment status styling class
function getPaymentStatusClass(status) {
    switch (status.toLowerCase()) {
        case 'paid':
            return 'px-3 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium';
        case 'pending':
            return 'px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium';
        case 'cancelled':
            return 'px-3 py-1 bg-red-100 text-red-800 rounded-full text-xs font-medium';
        default:
            return 'px-3 py-1 bg-gray-100 text-gray-800 rounded-full text-xs font-medium';
    }
}

// Generate bill from pending
function generateBillFromPending(rfidTag) {
    showSection('rfid-billing');
    setTimeout(() => {
        processBilling(rfidTag);
    }, 500);
}

// Process payment
function processPayment() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const total = document.getElementById('billTotal').textContent;
    const bookingId = document.getElementById('currentBookingId').value;
    
    // Debug logging
    console.log('Payment Method:', paymentMethod);
    console.log('Total:', total);
    console.log('Booking ID:', bookingId);
    console.log('Current Booking ID Element:', document.getElementById('currentBookingId'));
    
    if (!bookingId) {
        alert('Error: No booking ID found. Please try scanning the RFID tag again.');
        return;
    }
    
    if (!paymentMethod) {
        alert('Please select a payment method.');
        return;
    }
    
    // Check if billing information is loaded
    const billGeneration = document.getElementById('billGeneration');
    if (billGeneration.classList.contains('hidden')) {
        alert('Please load billing information first by scanning an RFID tag.');
        return;
    }
    
    // Check if total amount is valid
    if (!total || total === '₱0') {
        alert('Error: Invalid total amount. Please check the billing information.');
        return;
    }
    
    // Validate online payment reference if needed
    let reference = '';
    let platform = '';
    if (paymentMethod === 'online') {
        reference = document.getElementById('paymentReference').value.trim();
        platform = document.getElementById('paymentPlatform').value;
        
        if (!reference || !platform) {
            alert('Please enter both reference number and payment platform for online payments.');
            return;
        }
    }
    
    // Debug log
    const paymentData = {
        action: 'process_payment',
        booking_id: bookingId,
        payment_method: paymentMethod,
        payment_reference: reference,
        payment_platform: platform,
        send_receipt: true
    };
    console.log('Sending payment data:', paymentData);
    
    // Process payment and send receipt
    fetch(`${API_BASE}billing.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(paymentData)
    })
    .then(response => {
        console.log('Payment response status:', response.status);
        console.log('Payment response headers:', response.headers);
        return response.json();
    })
    .then(data => {
        console.log('Payment response data:', data);
        if (data.success) {
            // Update receipt status in the modal
            const receiptStatusElement = document.getElementById('receiptStatus');
            if (receiptStatusElement) {
                if (data.receipt_sent) {
                    receiptStatusElement.textContent = 'Receipt has been sent to customer email.';
                    receiptStatusElement.classList.add('text-green-600');
                } else {
                    receiptStatusElement.textContent = 'Could not send receipt to customer email. You can print it instead.';
                    receiptStatusElement.classList.add('text-yellow-600');
                }
            }
            
            // Store payment info for printing
            window.currentPaymentInfo = {
                booking_id: bookingId,
                payment_method: paymentMethod,
                payment_reference: reference,
                payment_platform: platform
            };
            
            // Show success modal
            document.getElementById('successModal').classList.remove('hidden');
            
            // Update receipt status in modal
            if (data.receipt_sent) {
                document.getElementById('receiptStatus').textContent = 'Receipt has been sent to customer email.';
                document.getElementById('receiptStatus').classList.remove('text-yellow-600');
                document.getElementById('receiptStatus').classList.add('text-green-600');
            } else {
                document.getElementById('receiptStatus').textContent = 'Could not send receipt email. Please try manual receipt.';
                document.getElementById('receiptStatus').classList.remove('text-green-600');
                document.getElementById('receiptStatus').classList.add('text-yellow-600');
            }
            
            // Update stats
            loadStats();
            loadPendingBills();
            loadPaymentProcessing();
        } else {
            alert('Payment processing failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing payment. Please try again.');
    });
    
    // In a real system, this would integrate with payment processors
    console.log(`Processing ${total} payment via ${paymentMethod}`);
}

// Print bill
function printBill() {
    window.print();
}

// Print receipt
function printReceipt() {
    if (!window.currentPaymentInfo) {
        alert('Payment information not available');
        return;
    }
    
    const { booking_id, payment_method, payment_reference, payment_platform } = window.currentPaymentInfo;
    
    // Open receipt in new window for printing
    const receiptUrl = `../api/billing.php?action=print_receipt&booking_id=${booking_id}&payment_method=${payment_method}&payment_reference=${payment_reference || ''}&payment_platform=${payment_platform || ''}`;
    
    const printWindow = window.open(receiptUrl, '_blank');
    
    // Automatically trigger print dialog when content loads
    if (printWindow) {
        printWindow.addEventListener('load', function() {
            printWindow.print();
        });
    }
}

// Save draft
function saveDraft() {
    alert('Bill draft saved successfully!');
}

// Generate report
function generateReport() {
    alert('Generating billing report... This feature will export today\'s transactions.');
}

// Close modal
function closeModal() {
    document.getElementById('successModal').classList.add('hidden');
    // Reset form
    document.getElementById('billGeneration').classList.add('hidden');
    document.getElementById('scannedTag').textContent = '---';
    document.getElementById('manualRfidInput').value = '';
    document.getElementById('paymentReference').value = '';
    document.getElementById('paymentPlatform').value = '';
    document.getElementById('onlinePaymentRef').classList.add('hidden');
    loadStats();
    loadPendingBills();
    loadPaymentProcessing();
}

// Close success modal
document.getElementById('closeSuccessModal')?.addEventListener('click', function() {
    closeModal();
});

// Refresh pending bills
function refreshPendingBills() {
    loadPendingBills();
    showNotification('Pending bills refreshed', 'success');
}

// Export pending bills
function exportPendingBills() {
    alert('Exporting pending bills... This feature will download a CSV file.');
}

// Show notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-4 rounded-xl shadow-lg z-50 transform translate-x-full transition-transform duration-300`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Remove after 4 seconds
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 4000);
}
