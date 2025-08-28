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
    // Only load the payment processing widget if its container exists on this page
    if (document.getElementById('paymentProcessingContainer')) {
        await loadPaymentProcessing();
    }
    
    // Initialize with manual billing section
    showSection('manual-billing');
    
    // Set up payment method change handler
    document.getElementById('paymentMethod').addEventListener('change', handlePaymentMethodChange);
    document.getElementById('logoutBtn')?.addEventListener('click', () => {
        try {
            localStorage.clear();
        } catch (_) {}
        window.location.replace('admin_staff_auth.html');
    });

    // Make stat cards clickable
    document.getElementById('pendingBillsCard')?.addEventListener('click', () => {
        showSection('pending-bills');
    });
    document.getElementById('processingBillsCard')?.addEventListener('click', () => {
        showSection('pending-bills');
        setTimeout(() => {
            const el = document.getElementById('pendingBillsStatusFilter');
            if (el) { el.value = 'in_progress'; el.dispatchEvent(new Event('change')); }
        }, 100);
    });
    document.getElementById('todayRevenueCard')?.addEventListener('click', () => {
        showSection('recent-transactions');
        // Optionally set date filter to today
        setTimeout(() => {
            const el = document.getElementById('dateFilter');
            if (el) { el.value = 'today'; el.dispatchEvent(new Event('change')); }
        }, 100);
    });
    document.getElementById('weekRevenueCard')?.addEventListener('click', () => {
        showSection('recent-transactions');
        setTimeout(() => {
            const el = document.getElementById('dateFilter');
            if (el) { el.value = 'week'; el.dispatchEvent(new Event('change')); }
        }, 100);
    });
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
    
    // Check if user has access to billing (admin or cashier). Support both top-level role and legacy staff_role.
    const isAdmin = role === 'admin';
    const isCashier = role === 'cashier' || staffRole === 'cashier';
    if (!isAdmin && !isCashier) {
        alert('Access denied. Only admin and cashier can access billing management.');
        redirectToAuth();
        return false;
    }

    // Set current user from localStorage
    currentUser = {
        id: localStorage.getItem('auth_user_id') || 'unknown',
        email: localStorage.getItem('auth_email') || '',
        username: localStorage.getItem('auth_username') || '',
        full_name: localStorage.getItem('auth_full_name') || '',
        role: role,
        staff_role: staffRole
    };
    
    updateUserInfo();
    return true;
}

// Update user information display
function updateUserInfo() {
    if (currentUser) {
        const preferredName = (currentUser.full_name && currentUser.full_name.trim())
            ? currentUser.full_name.trim()
            : ((currentUser.username && currentUser.username.trim())
                ? currentUser.username.trim()
                : (currentUser.email ? currentUser.email.split('@')[0] : 'User'));
        const userInitial = preferredName.charAt(0).toUpperCase();

        const nameEl = document.getElementById('userName');
        const roleEl = document.getElementById('userRole');
        const initEl = document.getElementById('userInitials');
        if (nameEl) nameEl.textContent = preferredName;
        const roleLabel = currentUser.role === 'admin' ? 'Admin' : (currentUser.role === 'cashier' || currentUser.staff_role === 'cashier' ? 'Cashier' : 'Staff');
        if (roleEl) roleEl.textContent = roleLabel;
        if (initEl) initEl.textContent = userInitial;
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
        // Fetch pending bills and transactions in parallel
        const [pendingRes, txRes] = await Promise.all([
            fetch(`${API_BASE}billing.php?action=get_pending_bills`),
            fetch(`${API_BASE}billing.php?action=get_transactions`)
        ]);
        const [pendingData, txData] = await Promise.all([
            pendingRes.json(),
            txRes.json()
        ]);

        // Pending/processing counts
        const bills = Array.isArray(pendingData?.pending_bills) ? pendingData.pending_bills : [];
        const pendingCount = bills.filter(b => (b.payment_status || 'pending') !== 'paid').length;
        const inProgressCount = bills.filter(b => b.status === 'in_progress').length;

        // Revenue: sum completed transactions amounts for today and last 7 days
        const txs = Array.isArray(txData?.transactions) ? txData.transactions : [];
        const now = new Date();
        const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
        const sum = (arr) => arr.reduce((acc, n) => acc + (parseFloat(n) || 0), 0);

        const completedTxs = txs.filter(t => (t.status || '').toLowerCase() === 'completed');
        const todayAmounts = completedTxs
            .filter(t => {
                const d = new Date(t.created_at);
                return d >= startOfToday;
            })
            .map(t => t.amount);
        const weekAmounts = completedTxs
            .filter(t => {
                const d = new Date(t.created_at);
                return d >= sevenDaysAgo;
            })
            .map(t => t.amount);

        const todayRevenue = sum(todayAmounts);
        const weekRevenue = sum(weekAmounts);

        document.getElementById('todayRevenue').textContent = '₱' + todayRevenue.toLocaleString(undefined, { maximumFractionDigits: 2 });
        document.getElementById('weekRevenue').textContent = '₱' + weekRevenue.toLocaleString(undefined, { maximumFractionDigits: 2 });
        document.getElementById('pendingBills').textContent = pendingCount;
        document.getElementById('processingBills').textContent = inProgressCount;
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Load pending bills
async function loadPendingBills() {
    try {
        const container = document.getElementById('pendingBillsContainer');
        
        // Show loading state
        container.innerHTML = '<div class="text-center py-8 text-gray-500">Loading pending bills...</div>';
        
        // Fetch real data from API
        const response = await fetch(`${API_BASE}billing.php?action=get_pending_bills`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
                    if (data.pending_bills && Array.isArray(data.pending_bills)) {
            if (data.pending_bills.length === 0) {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No pending bills found</div>';
                return;
            }
            
            // Calculate summary statistics
            const totalAmount = data.pending_bills.reduce((sum, bill) => sum + parseFloat(bill.total_amount), 0);
            const statusCounts = data.pending_bills.reduce((counts, bill) => {
                counts[bill.status] = (counts[bill.status] || 0) + 1;
                return counts;
            }, {});
            
            // Create summary header with search and filters
            const summaryHTML = `
                <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Pending Bills Summary</h3>
                        <span class="text-sm text-gray-500">Total: ${data.pending_bills.length} bills</span>
                        </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">₱${totalAmount.toFixed(2)}</div>
                            <div class="text-sm text-gray-500">Total Value</div>
                            </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600">${statusCounts.completed || 0}</div>
                            <div class="text-sm text-gray-500">Completed</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-yellow-600">${statusCounts.in_progress || 0}</div>
                            <div class="text-sm text-gray-500">In Progress</div>
                    </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">${statusCounts.checked_in || 0}</div>
                            <div class="text-sm text-gray-500">Checked In</div>
                        </div>
                    </div>
                    
                    <!-- Search and Filter Controls -->
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center">
                            <!-- Search Box -->
                            <div class="flex-1 min-w-0">
                                <div class="relative">
                                    <input type="text" 
                                           id="pendingBillsSearch" 
                                           placeholder="Search by pet name, RFID, customer name..." 
                                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold-500 focus:border-transparent text-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                </div>
            </div>
                            </div>
                            
                            <!-- Status Filter -->
                            <div class="flex-shrink-0">
                                <select id="pendingBillsStatusFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold-500 focus:border-transparent text-sm">
                                    <option value="">All Statuses</option>
                                    <option value="completed">Completed</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="checked_in">Checked In</option>
                                    <option value="ready_for_pickup">Ready for Pickup</option>
                                </select>
                            </div>
                            
                            <!-- Pet Type Filter -->
                            <div class="flex-shrink-0">
                                <select id="pendingBillsPetTypeFilter" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold-500 focus:border-transparent text-sm">
                                    <option value="">All Pet Types</option>
                                    <option value="dog">Dogs</option>
                                    <option value="cat">Cats</option>
                                </select>
                            </div>
                            
                            <!-- Sort Options -->
                            <div class="flex-shrink-0">
                                <select id="pendingBillsSort" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gold-500 focus:border-transparent text-sm">
                                    <option value="check_in_time_asc">Check-in Time (Oldest First)</option>
                                    <option value="check_in_time_desc">Check-in Time (Newest First)</option>
                                    <option value="total_amount_desc">Amount (Highest First)</option>
                                    <option value="total_amount_asc">Amount (Lowest First)</option>
                                    <option value="pet_name_asc">Pet Name (A-Z)</option>
                                    <option value="customer_name_asc">Customer Name (A-Z)</option>
                                </select>
                            </div>
                            
                            <!-- Clear Filters Button -->
                            <div class="flex-shrink-0">
                                <button onclick="clearPendingBillsFilters()" 
                                        class="px-4 py-2 text-sm font-medium text-gray-600 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 focus:ring-2 focus:ring-gold-500 focus:border-transparent transition-colors">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Store the data globally for filtering and sorting
            currentPendingBills = data.pending_bills;
            currentPendingBillsPage = 1;
            
            // Apply filters and display
            applyPendingBillsFilters();
            
            // Combine summary and bills
            container.innerHTML = summaryHTML + displayPendingBills();
            
            // Add event listeners for search and filters
            addPendingBillsEventListeners();
                
            } else {
                throw new Error('Invalid pending bills data received from server');
            }
        } else {
            const errorMessage = data.message || 'Unknown error occurred';
            container.innerHTML = `<div class="text-center py-8 text-red-500">Error loading pending bills: ${errorMessage}</div>`;
            console.error('API Error:', data);
        }
        
    } catch (error) {
        console.error('Error loading pending bills:', error);
        const errorMessage = error.message || 'Network or parsing error';
        const container = document.getElementById('pendingBillsContainer');
        container.innerHTML = `<div class="text-center py-8 text-red-500">Failed to load pending bills: ${errorMessage}</div>`;
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
            <div class="border border-${getPaymentColor(payment.type).border} bg-${getPaymentColor(payment.type).bg} rounded-lg p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-${getPaymentColor(payment.type).text} text-sm">${payment.status}</h3>
                    <span class="text-xs text-${getPaymentColor(payment.type).timeText}">${payment.time}</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <p class="text-xs text-${getPaymentColor(payment.type).content}"><strong>Pet:</strong> ${payment.pet}</p>
                        <p class="text-xs text-${getPaymentColor(payment.type).content}"><strong>Owner:</strong> ${payment.owner}</p>
                    </div>
                    <div>
                        <p class="text-xs text-${getPaymentColor(payment.type).content}"><strong>Method:</strong> ${payment.method}</p>
                        <p class="text-xs text-${getPaymentColor(payment.type).content}">
                            <strong>${payment.receipt ? 'Receipt:' : 'Card:'}</strong> ${payment.receipt || payment.card}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold text-${getPaymentColor(payment.type).amount}">₱${payment.amount.toLocaleString()}</p>
                        ${payment.type === 'processing' ? 
                            `<button class="mt-1 text-${getPaymentColor(payment.type).button} hover:text-${getPaymentColor(payment.type).buttonHover} text-xs underline">Cancel</button>` :
                            `<button class="mt-1 text-${getPaymentColor(payment.type).button} hover:text-${getPaymentColor(payment.type).buttonHover} text-xs underline">View Receipt</button>`
                        }
                    </div>
                </div>
            </div>
        `).join('');
        
        // Daily summary
        dailySummary.innerHTML = `
            <div class="flex justify-between text-xs">
                <span class="text-gray-600">Cash Payments:</span>
                <span class="font-medium">₱3,200</span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-600">Card Payments:</span>
                <span class="font-medium">₱2,800</span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-600">Online Payments:</span>
                <span class="font-medium">₱2,450</span>
            </div>
            <hr class="my-2 border-gray-300">
            <div class="flex justify-between text-sm font-bold">
                <span>Total:</span>
                <span class="text-gold-600">₱8,450</span>
            </div>
        `;
        
        // Payment methods chart
        paymentMethodsChart.innerHTML = `
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-500 rounded"></div>
                    <span class="text-gray-600">Cash</span>
                </div>
                <span class="font-medium">38%</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-blue-500 rounded"></div>
                    <span class="text-gray-600">Cards</span>
                </div>
                <span class="font-medium">33%</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-purple-500 rounded"></div>
                    <span class="text-gray-600">Online</span>
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
        case 'completed':
            return { border: 'green-200', bg: 'green-50', icon: 'green-100', text: 'green-600', badge: 'green-100', badgeText: 'green-800' };
        case 'in_progress':
            return { border: 'yellow-200', bg: 'yellow-50', icon: 'yellow-100', text: 'yellow-600', badge: 'yellow-100', badgeText: 'yellow-800' };
        case 'checked_in':
            return { border: 'blue-200', bg: 'blue-50', icon: 'blue-100', text: 'blue-600', badge: 'blue-100', badgeText: 'blue-800' };
        case 'ready_for_pickup':
            return { border: 'orange-200', bg: 'orange-50', icon: 'orange-100', text: 'orange-600', badge: 'orange-100', badgeText: 'orange-800' };
        default:
            return { border: 'gray-200', bg: 'gray-50', icon: 'gray-100', text: 'gray-600', badge: 'gray-100', badgeText: 'gray-800' };
    }
}

// Format status for display
function formatStatus(status) {
    switch (status) {
        case 'completed':
            return 'Completed';
        case 'in_progress':
            return 'In Progress';
        case 'checked_in':
            return 'Checked In';
        case 'ready_for_pickup':
            return 'Ready for Pickup';
        default:
            return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
}

// Get time information for display
function getTimeInfo(bill) {
    try {
        const checkinTime = new Date(bill.check_in_time);
        const estimatedTime = bill.estimated_completion ? new Date(bill.estimated_completion) : null;
        const actualTime = bill.actual_completion ? new Date(bill.actual_completion) : null;
        
        // Validate dates
        if (isNaN(checkinTime.getTime())) {
            return 'Check-in time unavailable';
        }
        
        const formatTime = (date) => date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const formatDate = (date) => date.toLocaleDateString();
        
        if (bill.status === 'completed' && actualTime && !isNaN(actualTime.getTime())) {
            const duration = Math.round((actualTime - checkinTime) / (1000 * 60));
            const hours = Math.floor(duration / 60);
            const minutes = duration % 60;
            return `Completed: ${formatTime(actualTime)} • Duration: ${hours}h ${minutes}m`;
        } else if (bill.status === 'in_progress' && estimatedTime && !isNaN(estimatedTime.getTime())) {
            return `Started: ${formatTime(checkinTime)} • Est. completion: ${formatTime(estimatedTime)}`;
        } else if (bill.status === 'checked_in') {
            return `Check-in: ${formatTime(checkinTime)} • Est. completion: ${estimatedTime && !isNaN(estimatedTime.getTime()) ? formatTime(estimatedTime) : 'TBD'}`;
        } else {
            return `Check-in: ${formatTime(checkinTime)}`;
        }
    } catch (error) {
        console.error('Error formatting time info:', error);
        return 'Time information unavailable';
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
        
        // Load specific data for certain sections
        if (sectionName === 'recent-transactions') {
            loadTransactions();
        } else if (sectionName === 'pending-bills') {
            loadPendingBills();
            startPendingBillsAutoRefresh();
        } else {
            // Stop auto-refresh for other sections
            stopPendingBillsAutoRefresh();
        }
    }
}

// Clear manual form
function clearManualForm() {
    document.getElementById('manualRfidInput').value = '';
    document.getElementById('manualBookingInput').value = '';
    document.getElementById('billGeneration').classList.add('hidden');
}

// Process billing
function processBilling(tagId = null) {
    const rfidTag = tagId || document.getElementById('manualRfidInput').value || document.getElementById('manualBookingInput').value;
    
    if (!rfidTag) {
        alert('Please enter an RFID tag ID or booking ID first.');
        return;
    }
    
    // Show loading state
    const billGeneration = document.getElementById('billGeneration');
    billGeneration.classList.add('hidden');
    
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
            }
        })
        .catch(error => {
            console.error('Error fetching billing info:', error);
            const errorMsg = 'Failed to fetch billing information. Please check your connection and try again.';
            alert(errorMsg);
        });
}
    
// Display billing information
function displayBillingInfo(pet, rfidTag) {
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
            <td class="px-4 py-2 text-xs text-gray-900">${service.name}</td>
            <td class="px-4 py-2 text-xs text-gray-600 text-right">₱${service.basePrice}</td>
            <td class="px-4 py-2 text-xs text-gray-600 text-center">${service.modifier}</td>
            <td class="px-4 py-2 text-xs font-medium text-gray-900 text-right">₱${service.amount}</td>
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
    
    // Calculate tax (12% of subtotal before discount)
    const tax = Math.round(subtotal * 0.12);
    const total = subtotal - discount;
    
    // Update display (tax inclusive - showing 12% tax)
    document.getElementById('billSubtotal').textContent = `₱${subtotal.toLocaleString()}`;
    document.getElementById('billDiscount').textContent = `₱${discount.toLocaleString()}`;
    document.getElementById('billTax').textContent = `₱${tax.toLocaleString()}`; // Show 12% tax
    document.getElementById('billTotal').textContent = `₱${total.toLocaleString()}`;
}

// Handle payment method change
function handlePaymentMethodChange() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const onlinePaymentRef = document.getElementById('onlinePaymentRef');
    const onlinePaymentReference = document.getElementById('onlinePaymentReference');
    const onlinePaymentFields = document.getElementById('onlinePaymentFields');
    const cashPaymentFields = document.getElementById('cashPaymentFields');
    const changeDisplay = document.getElementById('changeDisplay');
    
    // Hide all payment-specific fields first
    onlinePaymentReference.classList.add('hidden');
    onlinePaymentFields.classList.add('hidden');
    cashPaymentFields.classList.add('hidden');
    changeDisplay.classList.add('hidden');
    
    // Show relevant fields based on payment method
    if (paymentMethod === 'online') {
        onlinePaymentReference.classList.remove('hidden');
        onlinePaymentFields.classList.remove('hidden');
    } else if (paymentMethod === 'cash') {
        cashPaymentFields.classList.remove('hidden');
        calculateChange(); // Calculate change when switching to cash
    }
}

// Calculate change for cash payments
function calculateChange() {
    const paymentMethod = document.getElementById('paymentMethod').value;
    const total = document.getElementById('billTotal').textContent;
    const amountTendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    const changeDisplay = document.getElementById('changeDisplay');
    const changeAmount = document.getElementById('changeAmount');
    
    if (paymentMethod === 'cash' && amountTendered > 0) {
        const totalAmount = parseFloat(total.replace('₱', '').replace(',', ''));
        const change = amountTendered - totalAmount;
        
        if (change >= 0) {
            changeDisplay.classList.remove('hidden');
            changeAmount.textContent = `₱${change.toFixed(2)}`;
            changeAmount.className = 'text-lg font-bold text-green-600';
    } else {
            changeDisplay.classList.remove('hidden');
            changeAmount.textContent = `₱${Math.abs(change).toFixed(2)} (Insufficient)`;
            changeAmount.className = 'text-lg font-bold text-red-600';
        }
    } else {
        changeDisplay.classList.add('hidden');
    }
}

// Get payment status styling class
function getPaymentStatusClass(status) {
    switch (status.toLowerCase()) {
        case 'paid':
            return 'px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-medium';
        case 'pending':
            return 'px-2 py-0.5 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium';
        case 'cancelled':
            return 'px-2 py-0.5 bg-red-100 text-red-800 rounded-full text-xs font-medium';
        default:
            return 'px-2 py-0.5 bg-gray-100 text-gray-800 rounded-full text-xs font-medium';
    }
}

// Generate bill from pending
function generateBillFromPending(rfidTag) {
    showSection('manual-billing');
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
    
    // Validate payment method specific requirements
    let reference = '';
    let platform = '';
    let amountTendered = 0;
    let change = 0;
    
    if (paymentMethod === 'online') {
        reference = document.getElementById('paymentReference').value.trim();
        platform = document.getElementById('paymentPlatform').value;
        
        if (!reference || !platform) {
            alert('Please enter both reference number and payment platform for online payments.');
            return;
        }
    } else if (paymentMethod === 'cash') {
        amountTendered = parseFloat(document.getElementById('amountTendered').value) || 0;
        const totalAmount = parseFloat(total.replace('₱', '').replace(',', ''));
        
        if (amountTendered <= 0) {
            alert('Please enter the amount tendered for cash payment.');
            return;
        }
        
        if (amountTendered < totalAmount) {
            alert('Amount tendered is insufficient. Please enter a higher amount.');
            return;
        }
        
        change = amountTendered - totalAmount;
    }
    
    // Get the discount amount from the bill display
    const discountElement = document.getElementById('billDiscount');
    const discountText = discountElement ? discountElement.textContent : '₱0';
    const discountAmount = parseInt(discountText.replace('₱', '').replace(',', '')) || 0;
    
    // Debug log
    const paymentData = {
        action: 'process_payment',
        booking_id: bookingId,
        payment_method: paymentMethod,
        payment_reference: reference,
        payment_platform: platform,
        amount_tendered: amountTendered,
        change_amount: change,
        discount_amount: discountAmount,
        send_receipt: true
    };
    console.log('Sending payment data:', paymentData);
    console.log('Discount amount extracted:', discountAmount);
    console.log('Discount element text:', discountText);
    
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

// Print bill (before payment)
function printBill() {
    // Get current bill data
    const petName = document.getElementById('billPetName').textContent;
    const petBreed = document.getElementById('billPetBreed').textContent;
    const ownerName = document.getElementById('billOwnerName').textContent;
    const ownerPhone = document.getElementById('billOwnerPhone').textContent;
    const rfidTag = document.getElementById('billRfidTag').textContent;
    const checkinTime = document.getElementById('billCheckinTime').textContent;
    const subtotal = document.getElementById('billSubtotal').textContent;
    const discount = document.getElementById('billDiscount').textContent;
    const tax = document.getElementById('billTax').textContent;
    const total = document.getElementById('billTotal').textContent;
    
    // Get services from table
    const serviceRows = document.querySelectorAll('#serviceBreakdown tr');
    let servicesHTML = '';
    serviceRows.forEach(row => {
        const cells = row.cells;
        if (cells.length >= 4) {
            const serviceName = cells[0].textContent;
            const basePrice = cells[1].textContent;
            const modifier = cells[2].textContent;
            const amount = cells[3].textContent;
            servicesHTML += `
                <tr>
                    <td>${serviceName}</td>
                    <td>${basePrice}</td>
                    <td>${modifier}</td>
                    <td>${amount}</td>
                </tr>
            `;
        }
    });
    
    // Generate bill number
    const billNumber = 'BILL-' + new Date().getTime();
    
    // Create bill HTML
    const billHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Animates PH - Bill #${billNumber}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                .bill {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 1px solid #ddd;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #D4AF37;
                    padding-bottom: 10px;
                }
                .bill-details table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .bill-details td {
                    padding: 8px;
                    border-bottom: 1px solid #eee;
                }
                .services {
                    margin: 20px 0;
                    border-top: 1px solid #eee;
                    border-bottom: 1px solid #eee;
                    padding: 10px 0;
                }
                .services table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .services th, .services td {
                    padding: 8px;
                    text-align: left;
                    border-bottom: 1px solid #eee;
                }
                .services th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                }
                .total {
                    font-size: 18px;
                    font-weight: bold;
                    text-align: right;
                    margin-top: 20px;
                    border-top: 2px solid #D4AF37;
                    padding-top: 10px;
                }
                .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    text-align: center;
                    color: #777;
                }
                @media print {
                    body {
                        padding: 0;
                        margin: 0;
                    }
                    .bill {
                        border: none;
                        width: 100%;
                        max-width: 100%;
                    }
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="bill">
                <div class="header">
                    <h2>Animates PH - Bill</h2>
                    <p>Camaro Branch</p>
                </div>
                
                <div class="bill-details">
                    <table>
                        <tr>
                            <td><strong>Bill #:</strong></td>
                            <td>${billNumber}</td>
                        </tr>
                        <tr>
                            <td><strong>Date:</strong></td>
                            <td>${new Date().toLocaleDateString()}</td>
                        </tr>
                        <tr>
                            <td><strong>Time:</strong></td>
                            <td>${new Date().toLocaleTimeString()}</td>
                        </tr>
                        <tr>
                            <td><strong>Customer:</strong></td>
                            <td>${ownerName}</td>
                        </tr>
                        <tr>
                            <td><strong>Phone:</strong></td>
                            <td>${ownerPhone}</td>
                        </tr>
                        <tr>
                            <td><strong>Pet:</strong></td>
                            <td>${petName} (${petBreed})</td>
                        </tr>
                        <tr>
                            <td><strong>RFID:</strong></td>
                            <td>${rfidTag}</td>
                        </tr>
                        <tr>
                            <td><strong>Check-in Time:</strong></td>
                            <td>${checkinTime}</td>
                        </tr>
                    </table>
                </div>
                
                <div class="services">
                    <h3>Services</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Base Price</th>
                                <th>Modifier</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${servicesHTML}
                        </tbody>
                    </table>
                </div>
                
                <div class="total">
                    <div style="margin-bottom: 10px;">
                        <span>Subtotal: ${subtotal}</span>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <span>Discount: ${discount}</span>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <span>Tax (12%): ${tax}</span>
                    </div>
                    <div style="font-size: 20px;">
                        <span>Total: ${total}</span>
                    </div>
                </div>
                
                <div class="footer">
                    <p>This is a bill for services rendered. Payment is due upon completion.</p>
                    <p>© 2025 Animates PH. All rights reserved.</p>
                </div>
                
                <div class="no-print" style="text-align: center; margin-top: 30px;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #D4AF37; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Print Bill
                    </button>
                </div>
            </div>
        </body>
        </html>
    `;
    
    // Open bill in new window
    const billWindow = window.open('', '_blank');
    billWindow.document.write(billHTML);
    billWindow.document.close();
    
    // Auto print after content loads
    billWindow.addEventListener('load', function() {
        billWindow.print();
    });
}

// Print receipt (after payment)
function printReceipt() {
    if (!window.currentPaymentInfo) {
        alert('Payment information not available');
        return;
    }
    
    const { booking_id, payment_method, payment_reference, payment_platform } = window.currentPaymentInfo;
    
    // Get current bill data from the page
    const petName = document.getElementById('billPetName').textContent;
    const petBreed = document.getElementById('billPetBreed').textContent;
    const ownerName = document.getElementById('billOwnerName').textContent;
    const ownerPhone = document.getElementById('billOwnerPhone').textContent;
    const rfidTag = document.getElementById('billRfidTag').textContent;
    const checkinTime = document.getElementById('billCheckinTime').textContent;
    const subtotal = document.getElementById('billSubtotal').textContent;
    const discount = document.getElementById('billDiscount').textContent;
    const tax = document.getElementById('billTax').textContent;
    const total = document.getElementById('billTotal').textContent;
    
    // Get services from table
    const serviceRows = document.querySelectorAll('#serviceBreakdown tr');
    let servicesHTML = '';
    serviceRows.forEach(row => {
        const cells = row.cells;
        if (cells.length >= 4) {
            const serviceName = cells[0].textContent;
            const basePrice = cells[1].textContent;
            const modifier = cells[2].textContent;
            const amount = cells[3].textContent;
            servicesHTML += `
                <tr>
                    <td>${serviceName}</td>
                    <td>${basePrice}</td>
                    <td>${modifier}</td>
                    <td>${amount}</td>
                </tr>
            `;
        }
    });
    
    // Get payment details
    const amountTendered = document.getElementById('amountTendered').value || '';
    const changeAmount = document.getElementById('changeDisplay').textContent.replace('Change: ', '') || '';
    
    // Generate receipt number
    const receiptNumber = 'RECEIPT-' + new Date().getTime();
    
    // Create receipt HTML with same format as bill
    const receiptHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Animates PH - Receipt #${receiptNumber}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                }
                .receipt {
                    max-width: 800px;
                    margin: 0 auto;
                    border: 1px solid #ddd;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #D4AF37;
                    padding-bottom: 10px;
                }
                .receipt-details table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .receipt-details td {
                    padding: 8px;
                    border-bottom: 1px solid #eee;
                }
                .services {
                    margin: 20px 0;
                    border-top: 1px solid #eee;
                    border-bottom: 1px solid #eee;
                    padding: 10px 0;
                }
                .services table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .services th, .services td {
                    padding: 8px;
                    text-align: left;
                    border-bottom: 1px solid #eee;
                }
                .services th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                }
                .total {
                    font-size: 18px;
                    font-weight: bold;
                    text-align: right;
                    margin-top: 20px;
                    border-top: 2px solid #D4AF37;
                    padding-top: 10px;
                }
                .payment-info {
                    margin: 20px 0;
                    border-top: 1px solid #eee;
                    padding: 10px 0;
                }
                .payment-info table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .payment-info td {
                    padding: 8px;
                    border-bottom: 1px solid #eee;
                }
                .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    text-align: center;
                    color: #777;
                }
                @media print {
                    body {
                        padding: 0;
                        margin: 0;
                    }
                    .receipt {
                        border: none;
                        width: 100%;
                        max-width: 100%;
                    }
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="receipt">
                <div class="header">
                    <h2>Animates PH - Receipt</h2>
                    <p>Camaro Branch</p>
                </div>
                
                <div class="receipt-details">
                    <table>
                        <tr>
                            <td><strong>Receipt #:</strong></td>
                            <td>${receiptNumber}</td>
                        </tr>
                        <tr>
                            <td><strong>Date:</strong></td>
                            <td>${new Date().toLocaleDateString()}</td>
                        </tr>
                        <tr>
                            <td><strong>Time:</strong></td>
                            <td>${new Date().toLocaleTimeString()}</td>
                        </tr>
                        <tr>
                            <td><strong>Customer:</strong></td>
                            <td>${ownerName}</td>
                        </tr>
                        <tr>
                            <td><strong>Phone:</strong></td>
                            <td>${ownerPhone}</td>
                        </tr>
                        <tr>
                            <td><strong>Pet:</strong></td>
                            <td>${petName} (${petBreed})</td>
                        </tr>
                        <tr>
                            <td><strong>RFID:</strong></td>
                            <td>${rfidTag}</td>
                        </tr>
                        <tr>
                            <td><strong>Check-in Time:</strong></td>
                            <td>${checkinTime}</td>
                        </tr>
                    </table>
                </div>
                
                <div class="services">
                    <h3>Services</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Base Price</th>
                                <th>Modifier</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${servicesHTML}
                        </tbody>
                    </table>
                </div>
                
                <div class="total">
                    <div style="margin-bottom: 10px;">
                        <span>Subtotal: ${subtotal}</span>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <span>Discount: ${discount}</span>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <span>Tax (12%): ${tax}</span>
                    </div>
                    <div style="font-size: 20px;">
                        <span>Total: ${total}</span>
                    </div>
                </div>
                
                <div class="payment-info">
                    <h3>Payment Details</h3>
                    <table>
                        <tr>
                            <td><strong>Payment Method:</strong></td>
                            <td>${payment_method}</td>
                        </tr>
                        ${payment_reference ? `<tr><td><strong>Reference:</strong></td><td>${payment_reference}</td></tr>` : ''}
                        ${payment_platform ? `<tr><td><strong>Platform:</strong></td><td>${payment_platform}</td></tr>` : ''}
                        ${amountTendered ? `<tr><td><strong>Amount Tendered:</strong></td><td>₱${amountTendered}</td></tr>` : ''}
                        ${changeAmount ? `<tr><td><strong>Change:</strong></td><td>${changeAmount}</td></tr>` : ''}
                    </table>
                </div>
                
                <div class="footer">
                    <p>Thank you for choosing Animates PH!</p>
                    <p>© 2025 Animates PH. All rights reserved.</p>
                </div>
                
                <div class="no-print" style="text-align: center; margin-top: 30px;">
                    <button onclick="window.print()" style="padding: 10px 20px; background: #D4AF37; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Print Receipt
                    </button>
                </div>
            </div>
        </body>
        </html>
    `;
    
    // Open receipt in new window
    const receiptWindow = window.open('', '_blank');
    receiptWindow.document.write(receiptHTML);
    receiptWindow.document.close();
    
    // Auto print after content loads
    receiptWindow.addEventListener('load', function() {
        receiptWindow.print();
    });
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
    clearManualForm();
    document.getElementById('paymentReference').value = '';
    document.getElementById('paymentPlatform').value = '';
    document.getElementById('amountTendered').value = '';
    document.getElementById('onlinePaymentReference').classList.add('hidden');
    document.getElementById('onlinePaymentFields').classList.add('hidden');
    document.getElementById('cashPaymentFields').classList.add('hidden');
    document.getElementById('changeDisplay').classList.add('hidden');
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

// Auto-refresh pending bills every 30 seconds when section is visible
let pendingBillsRefreshInterval = null;

function startPendingBillsAutoRefresh() {
    if (pendingBillsRefreshInterval) {
        clearInterval(pendingBillsRefreshInterval);
    }
    pendingBillsRefreshInterval = setInterval(() => {
        const pendingSection = document.getElementById('pending-bills');
        if (pendingSection && !pendingSection.classList.contains('hidden')) {
            loadPendingBills();
        }
    }, 30000); // 30 seconds
}

function stopPendingBillsAutoRefresh() {
    if (pendingBillsRefreshInterval) {
        clearInterval(pendingBillsRefreshInterval);
        pendingBillsRefreshInterval = null;
    }
}

// Export pending bills
function exportPendingBills() {
    try {
        // Show loading notification
        showNotification('Preparing export...', 'info');
        
        // Create download link
        const exportUrl = `${API_BASE}billing.php?action=export_pending_bills`;
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = `pending_bills_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
        
        // Trigger download
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Show success notification
        showNotification('Export completed successfully!', 'success');
        
    } catch (error) {
        console.error('Error exporting pending bills:', error);
        showNotification('Failed to export pending bills', 'error');
    }
}

// ===== PENDING BILLS SEARCH, FILTER, AND SORT FUNCTIONS =====

// Apply filters and sorting to pending bills
function applyPendingBillsFilters() {
    const searchTerm = document.getElementById('pendingBillsSearch')?.value?.toLowerCase() || '';
    const statusFilter = document.getElementById('pendingBillsStatusFilter')?.value || '';
    const petTypeFilter = document.getElementById('pendingBillsPetTypeFilter')?.value || '';
    const sortOption = document.getElementById('pendingBillsSort')?.value || 'check_in_time_asc';
    
    // Filter bills
    filteredPendingBills = currentPendingBills.filter(bill => {
        const matchesSearch = !searchTerm || 
            bill.pet_name.toLowerCase().includes(searchTerm) ||
            bill.custom_rfid.toLowerCase().includes(searchTerm) ||
            bill.customer_name.toLowerCase().includes(searchTerm) ||
            bill.pet_breed.toLowerCase().includes(searchTerm);
        
        const matchesStatus = !statusFilter || bill.status === statusFilter;
        const matchesPetType = !petTypeFilter || bill.pet_type === petTypeFilter;
        
        return matchesSearch && matchesStatus && matchesPetType;
    });
    
    // Sort bills
    sortPendingBills(sortOption);
    
    // Reset to first page
    currentPendingBillsPage = 1;
}

// Sort pending bills based on selected option
function sortPendingBills(sortOption) {
    switch (sortOption) {
        case 'check_in_time_asc':
            filteredPendingBills.sort((a, b) => new Date(a.check_in_time) - new Date(b.check_in_time));
            break;
        case 'check_in_time_desc':
            filteredPendingBills.sort((a, b) => new Date(b.check_in_time) - new Date(a.check_in_time));
            break;
        case 'total_amount_desc':
            filteredPendingBills.sort((a, b) => parseFloat(b.total_amount) - parseFloat(a.total_amount));
            break;
        case 'total_amount_asc':
            filteredPendingBills.sort((a, b) => parseFloat(a.total_amount) - parseFloat(b.total_amount));
            break;
        case 'pet_name_asc':
            filteredPendingBills.sort((a, b) => a.pet_name.localeCompare(b.pet_name));
            break;
        case 'customer_name_asc':
            filteredPendingBills.sort((a, b) => a.customer_name.localeCompare(b.customer_name));
            break;
        default:
            filteredPendingBills.sort((a, b) => new Date(a.check_in_time) - new Date(b.check_in_time));
    }
}

// Display pending bills with pagination
function displayPendingBills() {
    if (!filteredPendingBills || filteredPendingBills.length === 0) {
        return '<div class="text-center py-8 text-gray-500">No pending bills match your search criteria</div>';
    }
    
    // Pagination
    const totalPages = Math.ceil(filteredPendingBills.length / pendingBillsPerPage);
    const startIndex = (currentPendingBillsPage - 1) * pendingBillsPerPage;
    const endIndex = startIndex + pendingBillsPerPage;
    const pageBills = filteredPendingBills.slice(startIndex, endIndex);
    
    // Create bills HTML
    const billsHTML = pageBills.map(bill => {
        const statusColor = getStatusColor(bill.status);
        const timeInfo = getTimeInfo(bill);
        const petIcon = bill.pet_type === 'cat' ? '🐱' : '🐕';
        const petSize = bill.pet_size ? ` (${bill.pet_size})` : '';
        const phoneDisplay = bill.customer_phone ? bill.customer_phone : 'No phone';
        const emailDisplay = bill.customer_email ? bill.customer_email : 'No email';
        
        // Get search term for highlighting
        const searchTerm = document.getElementById('pendingBillsSearch')?.value || '';
        
        return `
            <div class="border border-${statusColor.border} bg-${statusColor.bg} rounded-lg p-4 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between">
                    <div class="flex items-start space-x-3 flex-1">
                        <div class="w-12 h-12 bg-${statusColor.icon} rounded-full flex items-center justify-center text-${statusColor.text} text-xl">
                            ${petIcon}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-2 mb-2">
                                <h3 class="font-semibold text-gray-900 text-base">${highlightSearchTerm(bill.pet_name, searchTerm)} (${highlightSearchTerm(bill.pet_breed, searchTerm)})${petSize}</h3>
                                <span class="px-3 py-1 bg-${statusColor.badge} text-${statusColor.badgeText} rounded-full text-xs font-medium">${formatStatus(bill.status)}</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                                <div>
                                    <p class="text-gray-600"><span class="font-medium">Owner:</span> ${highlightSearchTerm(bill.customer_name, searchTerm)}</p>
                                    <p class="text-gray-600"><span class="font-medium">Phone:</span> ${phoneDisplay}</p>
                                    <p class="text-gray-600"><span class="font-medium">Email:</span> ${emailDisplay}</p>
                                </div>
                                <div>
                                    <p class="text-gray-600"><span class="font-medium">RFID:</span> <span class="font-mono bg-gray-100 px-2 py-1 rounded">${highlightSearchTerm(bill.custom_rfid, searchTerm)}</span></p>
                                    <p class="text-gray-600"><span class="font-medium">Booking ID:</span> #${bill.booking_id}</p>
                                    <p class="text-gray-600"><span class="font-medium">Services:</span></p>
                                </div>
                            </div>
                            <div class="mt-2">
                                <p class="text-xs text-gray-700 bg-gray-50 px-3 py-2 rounded border-l-4 border-${statusColor.border}">${bill.services || 'Standard Package'}</p>
                            </div>
                            <div class="mt-2">
                                <p class="text-xs text-gray-500">${timeInfo}</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-right ml-4">
                        <div class="bg-white rounded-lg p-3 border border-gray-200">
                            <p class="text-2xl font-bold text-gray-900">₱${parseFloat(bill.total_amount).toFixed(2)}</p>
                            <p class="text-xs text-gray-500 mb-2">Total Amount</p>
                            <div class="flex flex-col space-y-2">
                                ${bill.status === 'completed' ? 
                                    `<button onclick="generateBillFromPending('${bill.custom_rfid}')" class="bg-gold-500 hover:bg-gold-600 text-white px-3 py-2 rounded text-sm font-medium transition-colors shadow-sm">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Generate Bill
                                    </button>` :
                                    `<div class="px-3 py-2 bg-gray-100 text-gray-500 rounded text-sm font-medium">
                                        ${formatStatus(bill.status)}
                                    </div>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    // Add pagination controls if needed
    let paginationHTML = '';
    if (totalPages > 1) {
        paginationHTML = `
            <div class="bg-white rounded-lg border border-gray-200 p-4 mt-4">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing ${startIndex + 1} to ${Math.min(endIndex, filteredPendingBills.length)} of ${filteredPendingBills.length} results
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="previousPendingBillsPage()" 
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                ${currentPendingBillsPage === 1 ? 'disabled' : ''}>
                            Previous
                        </button>
                        <span class="px-3 py-2 text-sm text-gray-700">
                            Page ${currentPendingBillsPage} of ${totalPages}
                        </span>
                        <button onclick="nextPendingBillsPage()" 
                                class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                ${currentPendingBillsPage === totalPages ? 'disabled' : ''}>
                            Next
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    return billsHTML + paginationHTML;
}

// Pagination functions for pending bills
function previousPendingBillsPage() {
    if (currentPendingBillsPage > 1) {
        currentPendingBillsPage--;
        updatePendingBillsDisplay();
    }
}

function nextPendingBillsPage() {
    const totalPages = Math.ceil(filteredPendingBills.length / pendingBillsPerPage);
    if (currentPendingBillsPage < totalPages) {
        currentPendingBillsPage++;
        updatePendingBillsDisplay();
    }
}

// Update pending bills display without reloading the entire section
function updatePendingBillsDisplay() {
    const container = document.getElementById('pendingBillsContainer');
    if (container) {
        // Keep the summary header, only update the bills section
        const summarySection = container.querySelector('.bg-white.rounded-lg.border.border-gray-200.p-4.mb-6');
        if (summarySection) {
            container.innerHTML = summarySection.outerHTML + displayPendingBills();
        }
    }
}

// Add event listeners for pending bills search and filters
function addPendingBillsEventListeners() {
    // Search input
    const searchInput = document.getElementById('pendingBillsSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(() => {
            applyPendingBillsFilters();
            updatePendingBillsDisplay();
        }, 300));
    }
    
    // Status filter
    const statusFilter = document.getElementById('pendingBillsStatusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            applyPendingBillsFilters();
            updatePendingBillsDisplay();
        });
    }
    
    // Pet type filter
    const petTypeFilter = document.getElementById('pendingBillsPetTypeFilter');
    if (petTypeFilter) {
        petTypeFilter.addEventListener('change', () => {
            applyPendingBillsFilters();
            updatePendingBillsDisplay();
        });
    }
    
    // Sort options
    const sortSelect = document.getElementById('pendingBillsSort');
    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            applyPendingBillsFilters();
            updatePendingBillsDisplay();
        });
    }
}

// Clear all pending bills filters and reset search
function clearPendingBillsFilters() {
    // Reset form elements
    const searchInput = document.getElementById('pendingBillsSearch');
    const statusFilter = document.getElementById('pendingBillsStatusFilter');
    const petTypeFilter = document.getElementById('pendingBillsPetTypeFilter');
    const sortSelect = document.getElementById('pendingBillsSort');
    
    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = '';
    if (petTypeFilter) petTypeFilter.value = '';
    if (sortSelect) sortSelect.value = 'check_in_time_asc';
    
    // Reset filters and display
    applyPendingBillsFilters();
    updatePendingBillsDisplay();
    
    // Show notification
    showNotification('All filters cleared', 'success');
}

// Debounce function to limit API calls during search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Highlight search terms in text
function highlightSearchTerm(text, searchTerm) {
    if (!searchTerm) return text;
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    return text.replace(regex, '<mark class="bg-yellow-200 px-1 rounded">$1</mark>');
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
    
    notification.className = `fixed top-4 right-4 ${colors[type]} text-white px-4 py-2 rounded-lg shadow-lg z-50 transform translate-x-full transition-transform duration-300 text-sm`;
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

// ===== TRANSACTION MANAGEMENT FUNCTIONS =====

// Global variables for transactions
let currentTransactions = [];
let currentPage = 1;
const transactionsPerPage = 10;

// Global variables for pending bills
let currentPendingBills = [];
let filteredPendingBills = [];
let currentPendingBillsPage = 1;
const pendingBillsPerPage = 20;

// Load transactions from database
async function loadTransactions() {
    try {
        // Show loading state
        const tableBody = document.getElementById('transactionsTable');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Loading transactions...</td></tr>';
        }
        
        const response = await fetch(`${API_BASE}billing.php?action=get_transactions`);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        const data = await response.json();
        
        if (data.success) {
            if (data.transactions && Array.isArray(data.transactions)) {
                // Deduplicate by booking_id (fallback to rfid_tag)
                const seenKeys = new Set();
                currentTransactions = data.transactions.filter(t => {
                    const key = t.booking_id ?? t.rfid_tag;
                    if (seenKeys.has(key)) return false;
                    seenKeys.add(key);
                    return true;
                });
                displayTransactions();
                
                if (currentTransactions.length === 0) {
                    showNotification('No transactions found', 'info');
                }
            } else {
                throw new Error('Invalid transactions data received from server');
            }
        } else {
            const errorMessage = data.message || 'Unknown error occurred';
            showNotification('Failed to load transactions: ' + errorMessage, 'error');
            console.error('API Error:', data);
        }
    } catch (error) {
        console.error('Error loading transactions:', error);
        const errorMessage = error.message || 'Network or parsing error';
        showNotification('Failed to load transactions: ' + errorMessage, 'error');
    }
}

// Display transactions in table
function displayTransactions() {
    const tableBody = document.getElementById('transactionsTable');
    const searchTerm = document.getElementById('transactionSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const dateFilter = document.getElementById('dateFilter').value;
    
    // Filter transactions
    let filteredTransactions = currentTransactions.filter(transaction => {
        const matchesSearch = !searchTerm || 
            transaction.customer_name.toLowerCase().includes(searchTerm) ||
            transaction.pet_name.toLowerCase().includes(searchTerm) ||
            transaction.rfid_tag.toLowerCase().includes(searchTerm);
        
        const matchesStatus = !statusFilter || transaction.status === statusFilter;
        const matchesDate = filterByDate(transaction.created_at, dateFilter);
        
        return matchesSearch && matchesStatus && matchesDate;
    });
    
    // Pagination
    const totalPages = Math.ceil(filteredTransactions.length / transactionsPerPage);
    const startIndex = (currentPage - 1) * transactionsPerPage;
    const endIndex = startIndex + transactionsPerPage;
    const pageTransactions = filteredTransactions.slice(startIndex, endIndex);
    
    // Update pagination info
    document.getElementById('showingFrom').textContent = startIndex + 1;
    document.getElementById('showingTo').textContent = Math.min(endIndex, filteredTransactions.length);
    document.getElementById('totalTransactions').textContent = filteredTransactions.length;
    
    // Update pagination buttons
    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages;
    
    // Clear table
    tableBody.innerHTML = '';
    
    // Check if there are transactions to display
    if (pageTransactions.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No transactions found</td></tr>';
        return;
    }
    
    // Populate table
    pageTransactions.forEach(transaction => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 cursor-pointer';
        row.onclick = () => openReceiptModal(transaction.booking_id);
        
        const statusClass = getStatusClass(transaction.status);
        const statusText = getStatusText(transaction.status);
        
        row.innerHTML = `
            <td class="px-4 py-3 text-sm text-gray-900">
                ${formatDateTime(transaction.created_at)}
            </td>
            <td class="px-4 py-3 text-sm text-gray-900">
                ${transaction.customer_name}
            </td>
            <td class="px-4 py-3 text-sm text-gray-900">
                ${transaction.pet_name} (${transaction.pet_breed})
            </td>
            <td class="px-4 py-3 text-sm text-gray-900 font-mono">
                ${transaction.rfid_tag}
            </td>
            <td class="px-4 py-3 text-sm text-right text-gray-900 font-medium">
                ₱${parseFloat(transaction.amount).toFixed(2)}
            </td>
            <td class="px-4 py-3 text-sm text-center text-gray-900">
                ${transaction.payment_method}
            </td>
            <td class="px-4 py-3 text-center">
                <span class="px-2 py-1 text-xs font-medium rounded-full ${statusClass}">
                    ${statusText}
                </span>
            </td>
            <td class="px-4 py-3 text-center">
                ${transaction.status === 'completed' ? 
                    `<button onclick="event.stopPropagation(); voidTransaction(${transaction.id})" 
                             class="px-2 py-1 text-xs bg-red-100 hover:bg-red-200 text-red-700 rounded-lg transition-colors">Void</button>` : 
                    '<span class="text-gray-400 text-xs">-</span>'
                }
            </td>
        `;
        
        tableBody.appendChild(row);
    });
}

// Filter transactions by date
function filterByDate(transactionDate, filterType) {
    const date = new Date(transactionDate);
    const now = new Date();
    
    switch (filterType) {
        case 'today':
            return date.toDateString() === now.toDateString();
        case 'week':
            const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
            return date >= weekAgo;
        case 'month':
            return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
        case 'all':
        default:
            return true;
    }
}

// Get status styling class
function getStatusClass(status) {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'voided':
            return 'bg-red-100 text-red-800';
        case 'refunded':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

// Get status display text
function getStatusText(status) {
    switch (status) {
        case 'completed':
            return 'Completed';
        case 'voided':
            return 'Voided';
        case 'refunded':
            return 'Refunded';
        default:
            return 'Unknown';
    }
}

// Format date and time
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

// Void transaction
function voidTransaction(transactionId) {
    const transaction = currentTransactions.find(t => t.id === transactionId);
    if (!transaction) {
        showNotification('Transaction not found', 'error');
        return;
    }
    
    // Populate void modal
    document.getElementById('voidCustomerName').textContent = transaction.customer_name;
    document.getElementById('voidAmount').textContent = '₱' + parseFloat(transaction.amount).toFixed(2);
    document.getElementById('voidDate').textContent = formatDateTime(transaction.created_at);
    document.getElementById('voidTransactionId').textContent = transaction.transaction_reference;
    
    // Store transaction ID for void operation
    window.currentVoidTransactionId = transactionId;
    
    // Show modal
    document.getElementById('voidModal').classList.remove('hidden');
}

// Close void modal
function closeVoidModal() {
    document.getElementById('voidModal').classList.add('hidden');
    document.getElementById('voidForm').reset();
    window.currentVoidTransactionId = null;
}

// Handle void form submission
async function handleVoidSubmission(event) {
    event.preventDefault();
    
    const reason = document.getElementById('voidReason').value;
    const notes = document.getElementById('voidNotes').value;
    
    if (!reason) {
        showNotification('Please select a reason for voiding', 'warning');
        return;
    }
    
    try {
        const response = await fetch(`${API_BASE}billing.php?action=void_transaction`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                transaction_id: window.currentVoidTransactionId,
                reason: reason,
                notes: notes
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Transaction voided successfully', 'success');
            closeVoidModal();
            loadTransactions(); // Refresh the list
        } else {
            const errorMessage = data.message || 'Unknown error occurred';
            showNotification('Failed to void transaction: ' + errorMessage, 'error');
            console.error('API Error:', data);
        }
    } catch (error) {
        console.error('Error voiding transaction:', error);
        const errorMessage = error.message || 'Network or parsing error';
        showNotification('Failed to void transaction: ' + errorMessage, 'error');
    }
}

// Pagination functions
function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        displayTransactions();
    }
}

function nextPage() {
    const totalPages = Math.ceil(currentTransactions.length / transactionsPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        displayTransactions();
    }
}

// Refresh transactions
function refreshTransactions() {
    loadTransactions();
    showNotification('Transactions refreshed', 'success');
}

// Export transactions
function exportTransactions() {
    try {
        // Show loading notification
        showNotification('Preparing export...', 'info');
        
        // Create download link
        const exportUrl = `${API_BASE}billing.php?action=export_transactions`;
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = `transactions_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
        
        // Trigger download
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Show success notification
        showNotification('Export completed successfully!', 'success');
        
    } catch (error) {
        console.error('Error exporting transactions:', error);
        showNotification('Failed to export transactions', 'error');
    }
}

// Export transactions to Excel (auto-fit columns)
function exportTransactionsExcel() {
    try {
        showNotification('Preparing Excel export...', 'info');
        const exportUrl = `${API_BASE}billing.php?action=export_transactions_excel`;
        const link = document.createElement('a');
        link.href = exportUrl;
        link.download = `transactions_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.xls`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showNotification('Excel export started', 'success');
    } catch (error) {
        console.error('Error exporting transactions (Excel):', error);
        showNotification('Failed to export transactions (Excel)', 'error');
    }
}

// Event listeners for transaction management
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for search and filters
    document.getElementById('transactionSearch')?.addEventListener('input', function() {
        currentPage = 1; // Reset to first page
        displayTransactions();
    });
    
    document.getElementById('statusFilter')?.addEventListener('change', function() {
        currentPage = 1; // Reset to first page
        displayTransactions();
    });
    
    document.getElementById('dateFilter')?.addEventListener('change', function() {
        currentPage = 1; // Reset to first page
        displayTransactions();
    });
    
    // Add void form submission handler
    document.getElementById('voidForm')?.addEventListener('submit', handleVoidSubmission);
});

function openReceiptModal(bookingId) {
    if (!bookingId) return;
    const modal = document.getElementById('receiptModal');
    const frame = document.getElementById('receiptFrame');
    frame.dataset.bookingId = bookingId;
    // Show receipt exactly as standalone page would render (no scaling)
    frame.dataset.scale = '1';
    frame.style.transform = 'none';
    frame.style.width = '100%';
    frame.style.height = '100%';
    frame.onload = () => {
        try {
            const doc = frame.contentDocument || frame.contentWindow.document;
            // Allow the iframe to scroll exactly like a standalone page
            doc.body.style.overflow = 'auto';
            doc.documentElement.style.overflow = 'auto';
            // Hide thank-you section and print button when embedded in modal
            try {
                const thankYou = doc.querySelector('.thank-you');
                if (thankYou) thankYou.style.display = 'none';
                const printBtnSection = doc.querySelector('.no-print');
                if (printBtnSection) printBtnSection.style.display = 'none';
            } catch (_) {}
        } catch (e) {
            // Cross-origin safety: ignore
        }
    };
    // Load printable page in embed mode to hide modal-only footer/print
    frame.src = `${API_BASE}billing.php?action=print_receipt&booking_id=${encodeURIComponent(bookingId)}&embed=1`;
    modal.classList.remove('hidden');
}

function closeReceiptModal() {
    const modal = document.getElementById('receiptModal');
    const frame = document.getElementById('receiptFrame');
    frame.src = 'about:blank';
    modal.classList.add('hidden');
}

// zoomReceipt removed per request (fixed real-size view)

function openReceiptNewTab() {
    // Deprecated: opening in new tab removed; modal shows same printable layout embedded
}

function printReceiptFromModal() {
    const frame = document.getElementById('receiptFrame');
    try {
        if (frame && frame.contentWindow) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } else {
            throw new Error('No frame contentWindow');
        }
    } catch (e) {
        console.warn('Unable to print from iframe:', e);
        // Fallback: open standalone receipt without embed flag
        try {
            const src = frame ? frame.getAttribute('src') : '';
            if (src && src !== 'about:blank') {
                const standalone = src.replace(/([&?])embed=1(&|$)/, '$1').replace(/[&?]$/, '');
                window.open(standalone, '_blank');
            }
        } catch (_) {}
    }
}

// Fit-to-container logic removed to mirror standalone receipt exactly
