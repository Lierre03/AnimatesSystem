// API base URL - adjust this to your server location
const API_BASE = 'http://localhost/animates/api/';

// Sample tracking data for demo (will be replaced with real API calls)
let trackingData = {};
let currentUser = null;

// Appointment booking variables
let selectedServices = [];
let totalAmount = 0;
let servicesData = {};
let currentPetSize = '';
let currentAppointmentFilter = 'all';
let editSelectedServices = [];

// Prevent back navigation without logout
window.history.pushState(null, "", window.location.href);
// Prevent browser back button (without annoying warnings)
window.addEventListener('popstate', function (event) {
    // Check if user is authenticated before allowing navigation
    const token = localStorage.getItem('authToken');
    if (!token) {
        // If no token, redirect to auth
        redirectToAuth();
        return;
    }
    // If authenticated, push state again to prevent going back
    window.history.pushState(null, "", window.location.href);
});

// Allow normal browser shortcuts (removed annoying warnings)
document.addEventListener('keydown', function(e) {
    // Allow all normal browser shortcuts
    // Only prevent back button navigation
    if (e.keyCode === 8 && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        return false;
    }
});

// Removed browser reload prompt as requested

// Show notification using modal instead of alert
function showNotification(message, type = 'info') {
    // Create modal if it doesn't exist
    let notificationModal = document.getElementById('notificationModal');
    if (!notificationModal) {
        notificationModal = document.createElement('div');
        notificationModal.id = 'notificationModal';
        notificationModal.className = 'fixed inset-0 flex items-center justify-center z-50 hidden';
        notificationModal.innerHTML = `
            <div class="fixed inset-0 bg-black bg-opacity-50"></div>
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4 relative z-10">
                <div class="flex items-center mb-4">
                    <div id="notificationIcon" class="mr-3"></div>
                    <h3 id="notificationTitle" class="text-lg font-semibold"></h3>
                </div>
                <p id="notificationMessage" class="text-gray-700 mb-4"></p>
                <div class="flex justify-end">
                    <button id="notificationCloseBtn" class="px-4 py-2 bg-gold-500 text-white rounded hover:bg-gold-600 focus:outline-none">
                        Close
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(notificationModal);
        
        // Add event listener to close button
        document.getElementById('notificationCloseBtn').addEventListener('click', () => {
            notificationModal.classList.add('hidden');
        });
    }
    
    // Set icon and title based on notification type
    const iconElement = document.getElementById('notificationIcon');
    const titleElement = document.getElementById('notificationTitle');
    
    let icon = '';
    let title = '';
    let iconColor = '';
    
    switch(type) {
        case 'success':
            icon = '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
            title = 'Success';
            iconColor = 'text-green-500';
            break;
        case 'error':
            icon = '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
            title = 'Error';
            iconColor = 'text-red-500';
            break;
        case 'warning':
            icon = '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
            title = 'Warning';
            iconColor = 'text-yellow-500';
            break;
        case 'info':
        default:
            icon = '<svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>';
            title = 'Information';
            iconColor = 'text-blue-500';
            break;
    }
    
    iconElement.className = iconColor;
    iconElement.innerHTML = icon;
    titleElement.textContent = title;
    
    // Set message
    document.getElementById('notificationMessage').textContent = message;
    
    // Show modal
    notificationModal.classList.remove('hidden');
    
    // Log to console for debugging
    console.log(`${type.toUpperCase()}: ${message}`);
}

function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.section').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Show selected section
    document.getElementById(sectionId).classList.remove('hidden');
    
    // Update desktop nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('text-gold-600', 'font-semibold');
        link.classList.add('text-gray-700', 'font-medium');
    });
    
    // Update mobile nav links
    document.querySelectorAll('.mobile-nav-link').forEach(link => {
        link.classList.remove('text-gold-600', 'font-semibold');
        link.classList.add('text-gray-700', 'font-medium');
    });
    
    // Highlight active nav (desktop)
    const activeDesktopLink = document.querySelector(`[onclick="showSection('${sectionId}')"].nav-link`);
    if (activeDesktopLink) {
        activeDesktopLink.classList.remove('text-gray-700', 'font-medium');
        activeDesktopLink.classList.add('text-gold-600', 'font-semibold');
    }
    
    // Highlight active nav (mobile)
    const activeMobileLink = document.querySelector(`[onclick="showSection('${sectionId}')"].mobile-nav-link`);
    if (activeMobileLink) {
        activeMobileLink.classList.remove('text-gray-700', 'font-medium');
        activeMobileLink.classList.add('text-gold-600', 'font-semibold');
    }
    
    // Load section-specific data
    if (sectionId === 'history') {
        // Check if user is authenticated before loading history
        const token = localStorage.getItem('authToken');
        if (token) {
            loadUserBookingHistory('all');
        } else {
            // Show login required message
            const historyContainer = document.getElementById('historyContainer');
            historyContainer.innerHTML = `
                <div class="text-center py-12">
                    <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <p class="text-yellow-600 text-lg mb-2">Authentication Required</p>
                    <p class="text-gray-500 text-sm">Please log in to view your service history.</p>
                </div>
            `;
        }
    } else if (sectionId === 'my-appointments') {
        loadUserAppointments();
    }
}

function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    menu.classList.toggle('hidden');
}

// Enhanced authentication and session management
async function checkAuth() {
    const token = localStorage.getItem('authToken');
    
    if (!token) {
        redirectToAuth();
        return false;
    }

    try {
        const response = await fetch(`${API_BASE}auth.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ action: 'verify_token' })
        });

        const result = await response.json();
        
        if (result.success) {
            currentUser = {
                id: result.user_id,
                email: result.email
            };
            
            // Verify user has customer role
            const roleResponse = await fetch(`${API_BASE}auth.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ action: 'check_role' })
            });
            
            if (!roleResponse.ok) {
                throw new Error('Role verification failed');
            }
            
            updateUserWelcome();
            return true;
        } else {
            throw new Error('Token verification failed');
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        localStorage.removeItem('authToken');
        redirectToAuth();
        return false;
    }
}

function updateUserWelcome() {
    const welcomeElement = document.getElementById('userWelcome');
    const welcomeMobileElement = document.getElementById('userWelcomeMobile');
    if (currentUser) {
        const welcomeText = `Welcome, ${currentUser.email}`;
        if (welcomeElement) welcomeElement.textContent = welcomeText;
        if (welcomeMobileElement) welcomeMobileElement.textContent = welcomeText;
    }
}

function redirectToAuth() {
    // Clear any stored data
    localStorage.clear();
    // Force redirect to auth page
    window.location.replace('auth.html');
}

async function logout() {
    try {
        const token = localStorage.getItem('authToken');
        
        if (token) {
            await fetch(`${API_BASE}logout.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });
        }
        
        localStorage.clear();
        showNotification('Logged out successfully', 'success');
        
        setTimeout(() => {
            redirectToAuth();
        }, 1000);
        
    } catch (error) {
        console.error('Logout error:', error);
        localStorage.clear();
        redirectToAuth();
    }
}

// Updated pet tracking function for RFID search
async function trackPet() {
    const rfidInput = document.getElementById('rfidInput');
    const rfidValue = rfidInput.value.toUpperCase().trim();
    
    if (!rfidValue) {
        showNotification('Please enter an RFID tag ID.', 'warning');
        return;
    }

    try {
        showNotification('Tracking pet...', 'info');
        
        const token = localStorage.getItem('authToken');
        const response = await fetch(`${API_BASE}tracking.php?rfid=${rfidValue}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const result = await response.json();
        
        if (result.success && result.data) {
            const petInfo = result.data;
            showNotification(`Found ${petInfo.pet_name}! Status: ${petInfo.status}`, 'success');
            
            // Display detailed tracking info
            displayPetTrackingInfo(petInfo);
        } else {
            showNotification('RFID tag not found or no active booking. Please check the ID and try again.', 'error');
        }
    } catch (error) {
        console.error('Error tracking pet:', error);
        showNotification('Error connecting to tracking system. Please try again.', 'error');
    }
    
    rfidInput.value = '';
}

function displayPetTrackingInfo(petInfo) {
    const container = document.getElementById('activeBookingsContainer');
    const statusSteps = {
        'checked-in': { step: 1, label: 'Checked In', color: 'green' },
        'bathing': { step: 2, label: 'Bathing', color: 'yellow' },
        'grooming': { step: 3, label: 'Grooming', color: 'yellow' },
        'ready': { step: 4, label: 'Ready for Pickup', color: 'green' }
    };
    
    const currentStatus = statusSteps[petInfo.status] || { step: 1, label: petInfo.status, color: 'gray' };
    
    container.innerHTML = `
        <div class="border border-gray-200 rounded-xl p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-xl">🐾</div>
                    <div>
                        <h3 class="font-semibold text-gray-900">${petInfo.pet_name}</h3>
                        <p class="text-sm text-gray-600">${petInfo.breed} • RFID: ${petInfo.tag_id}</p>
                        <p class="text-xs text-gray-500">Owner: ${petInfo.owner_name}</p>
                    </div>
                </div>
                <span class="px-3 py-1 bg-${currentStatus.color}-100 text-${currentStatus.color}-800 rounded-full text-sm font-medium">
                    ${currentStatus.label}
                </span>
            </div>
            
            <!-- Progress Timeline -->
            <div class="space-y-3 mb-4">
                ${generateTimelineSteps(petInfo.status, petInfo.status_history)}
            </div>

            <!-- Services -->
            <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                <h4 class="font-medium text-gray-900 mb-2">Services</h4>
                <div class="space-y-1">
                    ${petInfo.services.map(service => `
                        <div class="flex justify-between text-sm">
                            <span>${service.name}</span>
                            <span>₱${service.price}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="border-t mt-2 pt-2 flex justify-between font-semibold">
                    <span>Total:</span>
                    <span>₱${petInfo.total_amount}</span>
                </div>
            </div>

            <!-- Estimated Completion -->
            ${petInfo.estimated_completion ? `
                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-blue-700">Estimated Completion:</span>
                        <span class="text-sm font-bold text-blue-900">${formatTime(petInfo.estimated_completion)}</span>
                    </div>
                </div>
            ` : ''}
            
            <div class="mt-4 text-center">
                <button onclick="loadUserBookings()" class="text-primary hover:text-blue-700 text-sm font-medium">
                    ← Back to All Bookings
                </button>
            </div>
        </div>
    `;
}

// Updated function to load user's bookings automatically
async function loadUserBookings() {
    try {
        const token = localStorage.getItem('authToken');
        const response = await fetch(`${API_BASE}tracking.php`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            document.getElementById('activeBookingsCount').textContent = result.data.length;
            displayAllActiveBookings(result.data);
        } else {
            document.getElementById('activeBookingsCount').textContent = '0';
            document.getElementById('activeBookingsContainer').innerHTML = `
                <div class="text-center py-8">
                    <div class="text-4xl mb-4">🐾</div>
                    <p class="text-gray-500">No active bookings found</p>
                    <p class="text-sm text-gray-400 mt-2">Your pets will appear here when they're being groomed</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading user bookings:', error);
        if (error.message.includes('403') || error.message.includes('401')) {
            // Authentication issue, redirect to login
            redirectToAuth();
            return;
        }
        document.getElementById('activeBookingsContainer').innerHTML = `
            <div class="text-center py-8">
                <div class="text-4xl mb-4">⚠️</div>
                <p class="text-red-500">Error loading bookings</p>
                <p class="text-sm text-gray-500 mt-2">Please refresh the page or try again later</p>
            </div>
        `;
    }
}

function displayAllActiveBookings(bookings) {
    const container = document.getElementById('activeBookingsContainer');
    
    if (bookings.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <div class="text-4xl mb-4">🐾</div>
                <p class="text-gray-500">No active bookings</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = `
    <div class="mb-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Active Bookings</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            ${bookings.map(booking => `
                <div class="border border-gray-200 rounded-xl p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-xl">🐾</div>
                            <div>
                                <h3 class="font-semibold text-gray-900">${booking.pet_name}</h3>
                                <p class="text-sm text-gray-600">${booking.breed} • ${booking.tag_id}</p>
                                <p class="text-xs text-gray-500">${booking.owner_name}</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 ${getStatusColor(booking.status)} rounded-full text-sm font-medium">
                            ${booking.status.charAt(0).toUpperCase() + booking.status.slice(1).replace('-', ' ')}
                        </span>
                    </div>
                    <div class="text-center">
                        <button onclick="trackSpecificPet('${booking.tag_id}')" 
                                class="bg-primary hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                            View Details
                        </button>
                    </div>
                </div>
            `).join('')}
        </div>
    </div>
`;
}

function getStatusColor(status) {
    const statusColors = {
        'checked-in': 'bg-blue-100 text-blue-800',
        'bathing': 'bg-purple-100 text-purple-800', 
        'grooming': 'bg-orange-100 text-orange-800',
        'ready': 'bg-green-100 text-green-800',
        'completed': 'bg-gray-100 text-gray-800'
    };
    
    return statusColors[status] || 'bg-yellow-100 text-yellow-800';
}

function getStatusColor(status) {
    const statusColors = {
        'checked-in': 'bg-blue-100 text-blue-800',
        'bathing': 'bg-purple-100 text-purple-800', 
        'grooming': 'bg-orange-100 text-orange-800',
        'ready': 'bg-green-100 text-green-800',
        'completed': 'bg-gray-100 text-gray-800'
    };
    
    return statusColors[status] || 'bg-yellow-100 text-yellow-800';
}

async function trackSpecificPet(rfidTag) {
    if (!rfidTag) {
        showNotification('Invalid RFID tag', 'warning');
        return;
    }

    try {
        showNotification('Loading pet details...', 'info');
        
        const token = localStorage.getItem('authToken');
        const response = await fetch(`${API_BASE}tracking.php?rfid=${rfidTag}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        const result = await response.json();
        
        if (result.success && result.data) {
            const petInfo = result.data;
            showNotification(`Found ${petInfo.pet_name}! Status: ${petInfo.status}`, 'success');
            
            // Display detailed tracking info
            displayPetTrackingInfo(petInfo);
        } else {
            showNotification('RFID tag not found or no active booking. Please check the ID and try again.', 'error');
        }
    } catch (error) {
        console.error('Error tracking pet:', error);
        showNotification('Error connecting to tracking system. Please try again.', 'error');
    }
}

function generateTimelineSteps(currentStatus, statusHistory) {
    const steps = [
        { key: 'checked-in', name: 'Check-in Complete', icon: '✓' },
        { key: 'bathing', name: 'Bathing', icon: '🛁' },
        { key: 'grooming', name: 'Grooming', icon: '✂️' },
        { key: 'ready', name: 'Ready for Pickup', icon: '✅' }
    ];
    
    const statusOrder = ['checked-in', 'bathing', 'grooming', 'ready'];
    const currentIndex = statusOrder.indexOf(currentStatus);
    
    return steps.map((step, index) => {
        const isCompleted = index <= currentIndex;
        const isCurrent = index === currentIndex;
        const statusInfo = statusHistory.find(h => h.status === step.key);
        
        return `
            <div class="flex items-center">
                <div class="w-6 h-6 ${isCompleted ? 'bg-green-500' : isCurrent ? 'bg-yellow-500' : 'bg-gray-300'} rounded-full flex items-center justify-center mr-3">
                    ${isCompleted ? 
                        '<svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>' :
                        isCurrent ? '<div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>' :
                        '<div class="w-2 h-2 bg-gray-500 rounded-full"></div>'
                    }
                </div>
                <div class="flex-1">
                    <p class="font-medium ${isCompleted ? 'text-gray-900' : 'text-gray-600'}">${step.name}</p>
                    ${statusInfo ? `<p class="text-sm text-gray-600">${formatTime(statusInfo.created_at)}</p>` : 
                     isCurrent ? '<p class="text-sm text-gray-600">In progress...</p>' :
                     '<p class="text-sm text-gray-500">Pending</p>'}
                </div>
            </div>
        `;
    }).join('');
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
}

// History filter function
function filterHistory(period) {
    // Check if user is authenticated first
    const token = localStorage.getItem('authToken');
    if (!token) {
        showNotification('Please log in to view your service history', 'warning');
        return;
    }
    
    // Update button styles
    event.target.parentElement.querySelectorAll('button').forEach(btn => {
        btn.classList.remove('bg-gold-500', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });
    
    event.target.classList.remove('bg-gray-200', 'text-gray-700');
    event.target.classList.add('bg-gold-500', 'text-white');
    
    // Load user booking history with the selected filter
    loadUserBookingHistory(period);
}

// Load user booking history
async function loadUserBookingHistory(period = 'all') {
    const historyContainer = document.getElementById('historyContainer');
    
    // Show loading state
    historyContainer.innerHTML = `
        <div class="text-center py-12">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <p class="text-gray-500 text-lg">Loading service history...</p>
        </div>
    `;
    
    try {
        const token = localStorage.getItem('authToken');
        if (!token) {
            throw new Error('No authentication token found');
        }
        
        const response = await fetch(`http://localhost/animates/api/bookings.php?period=${period}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            renderBookingHistory(data.bookings, period);
        } else {
            throw new Error(data.error || 'Failed to load booking history');
        }
        
    } catch (error) {
        console.error('Error loading booking history:', error);
        historyContainer.innerHTML = `
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <p class="text-red-500 text-lg mb-2">Failed to load service history</p>
                <p class="text-gray-500 text-sm">${error.message}</p>
            </div>
        `;
    }
}

// Render booking history
function renderBookingHistory(bookings, period) {
    const historyContainer = document.getElementById('historyContainer');
    
    if (!bookings || bookings.length === 0) {
        const periodText = period === 'all' ? 'all time' : period === 'month' ? 'this month' : 'this year';
        historyContainer.innerHTML = `
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <p class="text-gray-500 text-lg mb-2">No service history found</p>
                <p class="text-gray-400 text-sm">You haven't had any grooming services ${periodText}.</p>
            </div>
        `;
        return;
    }
    
    let historyHTML = `
        <div class="space-y-6">
            <div class="flex justify-between items-center">
                <p class="text-sm text-gray-600">Showing ${bookings.length} service${bookings.length !== 1 ? 's' : ''}</p>
            </div>
        </div>
    `;
    
    bookings.forEach(booking => {
        const statusColor = getStatusColor(booking.status);
        const statusIcon = getStatusIcon(booking.status);
        
        historyHTML += `
            <div class="bg-white rounded-xl border border-gray-200 p-6 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="text-lg font-semibold text-gray-900">${booking.pet_name || 'Unknown Pet'}</h3>
                            <span class="px-3 py-1 rounded-full text-xs font-medium ${statusColor}">
                                ${statusIcon} ${booking.status.replace('_', ' ').toUpperCase()}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-1">
                            <span class="font-medium">Pet:</span> ${booking.pet_breed || 'Unknown'} ${booking.pet_type || ''} (${booking.pet_size || 'Unknown size'})
                        </p>
                        <p class="text-sm text-gray-600 mb-1">
                            <span class="font-medium">Booking Type:</span> ${booking.booking_type === 'walk_in' ? 'Walk-in' : 'Online'}
                        </p>
                        <p class="text-sm text-gray-600">
                            <span class="font-medium">Created:</span> ${booking.created_at}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold text-gold-600">₱${parseFloat(booking.total_amount).toFixed(2)}</p>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">Service Total:</h4>
                    <div class="flex flex-wrap gap-2">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            Grooming Service - ₱${parseFloat(booking.total_amount).toFixed(2)}
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-600"><span class="font-medium">Check-in:</span> ${booking.check_in_time || 'N/A'}</p>
                        ${booking.estimated_completion ? `<p class="text-gray-600"><span class="font-medium">Estimated Completion:</span> ${booking.estimated_completion}</p>` : ''}
                    </div>
                    <div>
                        ${booking.actual_completion ? `<p class="text-gray-600"><span class="font-medium">Actual Completion:</span> ${booking.actual_completion}</p>` : ''}
                        ${booking.pickup_time ? `<p class="text-gray-600"><span class="font-medium">Pickup Time:</span> ${booking.pickup_time}</p>` : ''}
                    </div>
                </div>
                
                ${booking.staff_notes ? `
                    <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-700">
                            <span class="font-medium">Staff Notes:</span> ${booking.staff_notes}
                        </p>
                    </div>
                ` : ''}
            </div>
        `;
    });
    
    historyContainer.innerHTML = historyHTML;
}

// Get status color for booking status
function getStatusColor(status) {
    const colors = {
        'checked-in': 'bg-blue-100 text-blue-800',
        'bathing': 'bg-purple-100 text-purple-800',
        'grooming': 'bg-yellow-100 text-yellow-800',
        'ready': 'bg-green-100 text-green-800',
        'completed': 'bg-emerald-100 text-emerald-800',
        'cancelled': 'bg-red-100 text-red-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

// Get status icon for booking status
function getStatusIcon(status) {
    const icons = {
        'checked-in': '📋',
        'bathing': '🛁',
        'grooming': '✂️',
        'ready': '✅',
        'completed': '🎉',
        'cancelled': '❌'
    };
    return icons[status] || '📋';
}



// Global variables for edit functionality

// Show notification modal instead of alert
function showNotification(message, type = 'info') {
    const modal = document.getElementById('notificationModal');
    const icon = document.getElementById('notificationIcon');
    const title = document.getElementById('notificationTitle');
    const messageEl = document.getElementById('notificationMessage');
    
    // Set icon and colors based on type
    const config = {
        success: {
            icon: '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>',
            bgColor: 'bg-green-500',
            title: 'Success'
        },
        error: {
            icon: '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>',
            bgColor: 'bg-red-500',
            title: 'Error'
        },
        warning: {
            icon: '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>',
            bgColor: 'bg-yellow-500',
            title: 'Warning'
        },
        info: {
            icon: '<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            bgColor: 'bg-blue-500',
            title: 'Information'
        }
    };
    
    const configData = config[type] || config.info;
    
    icon.className = `w-8 h-8 rounded-full flex items-center justify-center mr-3 ${configData.bgColor}`;
    icon.innerHTML = configData.icon;
    title.textContent = configData.title;
    messageEl.textContent = message;
    
    modal.classList.remove('hidden');
}

// Close notification modal
function closeNotificationModal() {
    document.getElementById('notificationModal').classList.add('hidden');
}

// Close edit modal
function closeEditModal() {
    document.getElementById('editAppointmentModal').classList.add('hidden');
    editSelectedServices = [];
}

// Initialize page
document.addEventListener('DOMContentLoaded', async function() {
    console.log('Customer portal initializing...');
    
    try {
        // Always load services first (no authentication required)
        console.log('Loading services...');
        await loadServicesFromDatabase();
        
        // Check authentication for other features
        console.log('Checking authentication...');
        const isAuthenticated = await checkAuth();
        if (!isAuthenticated) {
            console.log('Not authenticated, showing appointment section only');
            // Still show services but disable authenticated features
            showSection('appointment');
            return;
        }
        
        // Set tracking as default active section
        showSection('tracking');
        
        // Load user's bookings automatically
        loadUserBookings();
        
        // Load user appointments (requires authentication)
        await loadUserAppointments();
        
        // Add event listeners for appointment functionality
        addAppointmentEventListeners();
        
        // Add event listener to RFID input for Enter key (if it exists)
        const rfidInput = document.getElementById('rfidInput');
        if (rfidInput) {
            rfidInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    trackPet();
                }
            });
        }
        
        // Auto-reload page every 30 seconds for real-time updates
        setInterval(() => {
            // Only reload if we're on tracking section and showing all bookings
            if (!document.getElementById('tracking').classList.contains('hidden')) {
                const container = document.getElementById('activeBookingsContainer');
                if (container.innerHTML.includes('Your Active Bookings') || container.innerHTML.includes('No active bookings')) {
                    showNotification('Refreshing for latest updates...', 'info');
                    setTimeout(() => {
                        window.location.href = window.location.href;
                    }, 1000);
                }
            }
        }, 30000);
        
    } catch (error) {
        console.error('Error during initialization:', error);
        // Even if there's an error, try to show services
        showSection('appointment');
    }
});

// RFID Scanner Integration (for when you get the device)
function handleRFIDScan(tagId) {
    // This function will be called when RFID device detects a tag
    const rfidInput = document.getElementById('rfidInput');
    if (rfidInput) {
        rfidInput.value = tagId;
        trackPet();
    }
}

// Set minimum date to today for appointment booking
function setMinimumDate() {
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('preferredDate');
    if (dateInput) {
        dateInput.setAttribute('min', today);
        dateInput.setAttribute('value', today); // Set default to today
        console.log('Set minimum date to:', today);
        
        // Add event listeners to prevent past date selection
        dateInput.addEventListener('input', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to start of day
            
            if (selectedDate < today) {
                alert('Invalid selection. Please choose today\'s date or a later date.');
                this.value = today.toISOString().split('T')[0];
            }
        });
        
        dateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to start of day
            
            if (selectedDate < today) {
                alert('Please select a date today or in the future.');
                this.value = today.toISOString().split('T')[0];
            }
        });
        
        // Prevent manual typing of past dates
        dateInput.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' || e.key === 'Delete') {
                // Allow backspace and delete
                return;
            }
            
            // Prevent typing if it would result in a past date
            const currentValue = this.value;
            const cursorPosition = this.selectionStart;
            const newValue = currentValue.slice(0, cursorPosition) + e.key + currentValue.slice(cursorPosition);
            
            if (newValue.length >= 10) { // Date format is YYYY-MM-DD
                const testDate = new Date(newValue);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (testDate < today) {
                    e.preventDefault();
                }
            }
        });
    }
}









// Add these functions to your existing customer_portal.js file

// Initialize appointments section
async function initializeAppointments() {
    try {
        // Load staff members
        await loadStaffMembers();
        
        // Load services
        await loadServices();
        
        // Set minimum date to today
        setMinimumDate();
        
        // Initialize pet type change handler
        initializePetTypeHandler();
        
        // Initialize service selection handlers
        initializeServiceHandlers();
        
    } catch (error) {
        console.error('Error initializing appointments:', error);
        showNotification('Error loading appointment data', 'error');
    }
}

// Load staff members for preferred staff dropdown
async function loadStaffMembers() {
    try {
        const token = localStorage.getItem('authToken');
        const response = await fetch(`${API_BASE}appointments.php?action=get_staff`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            const staffSelect = document.querySelector('select[name="preferredStaff"]');
            if (staffSelect) {
                // Clear existing options except "No Preference"
                staffSelect.innerHTML = '<option value="">No Preference</option>';
                
                result.data.forEach(staff => {
                    const option = document.createElement('option');
                    option.value = staff.id;
                    option.textContent = `${staff.first_name} ${staff.last_name}`;
                    staffSelect.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading staff members:', error);
    }
}

// Load services and populate service sections
async function loadServices() {
    try {
        const token = localStorage.getItem('authToken');
        const response = await fetch(`${API_BASE}appointments.php?action=get_services`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            populateServices(result.data);
        }
    } catch (error) {
        console.error('Error loading services:', error);
    }
}

// Populate services in the form
function populateServices(services) {
    const basicServices = services.filter(s => s.category === 'basic');
    const premiumServices = services.filter(s => s.category === 'premium');
    const addonServices = services.filter(s => s.category === 'addon');
    
    // Populate basic services
    populateServiceSection('basicServices', basicServices);
    
    // Populate premium services  
    populateServiceSection('premiumServices', premiumServices);
    
    // Populate addon services
    populateServiceSection('addonServices', addonServices);
}

// Replace the existing populateServiceSection function with this updated version:
function populateServiceSection(containerId, services) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    const servicesContainer = container.querySelector('.space-y-4');
    if (!servicesContainer) return;
    
    servicesContainer.innerHTML = '';
    
    services.forEach(service => {
        const serviceHtml = `
            <label class="flex items-center p-4 bg-white/80 rounded-lg border border-blue-200 hover:border-blue-300 transition-all duration-200 cursor-pointer hover:shadow-md">
                <input type="checkbox" class="service-checkbox w-5 h-5 text-primary rounded" data-service="${service.name}" data-price="${service.price}" data-id="${service.id}">
                <div class="ml-4 flex-1 flex justify-between items-center">
                    <div>
                        <span class="font-medium text-gray-900">${service.name}</span>
                        ${service.description ? `<p class="text-sm text-gray-600">${service.description}</p>` : ''}
                    </div>
                    <span class="text-lg font-bold text-primary">₱${parseFloat(service.price).toFixed(2)}</span>
                </div>
            </label>
        `;
        servicesContainer.insertAdjacentHTML('beforeend', serviceHtml);
    });
}

// Set minimum date to today (simplified version)
function setMinimumDate() {
    const dateInput = document.querySelector('input[type="date"]');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.min = today;
        console.log('Set minimum date for main form:', today);
    }
}

// Initialize pet type change handler
function initializePetTypeHandler() {
    const petTypeSelect = document.querySelector('select[name="petType"]');
    const petBreedSelect = document.querySelector('select[name="petBreed"]');
    
    if (!petTypeSelect || !petBreedSelect) return;
    
    petTypeSelect.addEventListener('change', async function() {
        const petType = this.value.toLowerCase();
        
        if (petType === 'others') {
            petBreedSelect.innerHTML = '<option value="">Enter breed manually</option>';
            petBreedSelect.disabled = true;
            // You might want to show a text input for custom breed here
        } else if (petType) {
            await loadBreeds(petType, petBreedSelect);
        } else {
            petBreedSelect.innerHTML = '<option value="">First select pet type</option>';
            petBreedSelect.disabled = true;
        }
    });
}

// Load breeds based on pet type (from check_in.js logic)
async function loadBreeds(petType, breedSelect) {
    try {
        breedSelect.innerHTML = '<option value="">Loading breeds...</option>';
        breedSelect.disabled = false;
        
        let apiUrl = '';
        if (petType === 'dog') {
            apiUrl = 'https://dog.ceo/api/breeds/list/all';
        } else if (petType === 'cat') {
            apiUrl = 'https://api.thecatapi.com/v1/breeds';
        }
        
        if (!apiUrl) {
            breedSelect.innerHTML = '<option value="">Select breed</option>';
            return;
        }
        
        const response = await fetch(apiUrl);
        const data = await response.json();
        
        breedSelect.innerHTML = '<option value="">Select breed</option>';
        
        if (petType === 'dog') {
            const breeds = Object.keys(data.message);
            breeds.forEach(breed => {
                const option = document.createElement('option');
                option.value = breed;
                option.textContent = breed.charAt(0).toUpperCase() + breed.slice(1);
                breedSelect.appendChild(option);
            });
        } else if (petType === 'cat') {
            data.forEach(breed => {
                const option = document.createElement('option');
                option.value = breed.name;
                option.textContent = breed.name;
                breedSelect.appendChild(option);
            });
        }
        
    } catch (error) {
        console.error('Error loading breeds:', error);
        breedSelect.innerHTML = '<option value="">Error loading breeds</option>';
    }
}

// Initialize service selection handlers
function initializeServiceHandlers() {
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('service-checkbox')) {
            updateAppointmentTotal();
        }
    });
}

// Replace the existing updateAppointmentTotal function:
function updateAppointmentTotal() {
    let total = 0;
    const checkedServices = document.querySelectorAll('.service-checkbox:checked');
    
    checkedServices.forEach(checkbox => {
        total += parseFloat(checkbox.dataset.price);
    });
    
    // Find the total element - it might be in different locations
    const totalElement = document.querySelector('.text-lg.font-bold.text-primary:last-child') || 
                        document.querySelector('#appointmentTotal') ||
                        document.querySelector('[class*="text-lg"][class*="font-bold"][class*="text-primary"]');
    
    if (totalElement) {
        totalElement.textContent = `₱${total.toFixed(2)}`;
    }
}



async function loadBreeds(petType, breedSelect) {
    try {
        breedSelect.innerHTML = '<option value="">Loading breeds...</option>';
        breedSelect.disabled = false;
        
        let apiUrl = '';
        if (petType === 'dog') {
            apiUrl = 'https://dog.ceo/api/breeds/list/all';
        } else if (petType === 'cat') {
            apiUrl = 'https://api.thecatapi.com/v1/breeds';
        }
        
        if (!apiUrl) {
            breedSelect.innerHTML = '<option value="">Select breed</option>';
            return;
        }
        
        const response = await fetch(apiUrl);
        const data = await response.json();
        
        breedSelect.innerHTML = '<option value="">Select breed</option>';
        
        if (petType === 'dog') {
            const breeds = Object.keys(data.message);
            breeds.sort().forEach(breed => {
                const option = document.createElement('option');
                option.value = breed;
                option.textContent = breed.charAt(0).toUpperCase() + breed.slice(1).replace(/[-_]/g, ' ');
                breedSelect.appendChild(option);
            });
        } else if (petType === 'cat') {
            data.sort((a, b) => a.name.localeCompare(b.name)).forEach(breed => {
                const option = document.createElement('option');
                option.value = breed.name;
                option.textContent = breed.name;
                breedSelect.appendChild(option);
            });
        }
        
    } catch (error) {
        console.error('Error loading breeds:', error);
        breedSelect.innerHTML = '<option value="">Error loading breeds</option>';
    }
}

// Handle appointment form submission
async function handleAppointmentSubmission(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validate required fields
    const petName = formData.get('petName');
    const petType = formData.get('petType');
    const petBreed = formData.get('petBreed');
    const preferredDate = formData.get('preferredDate');
    const preferredTime = formData.get('preferredTime');
    
    if (!petName || !petType || !petBreed || !preferredDate || !preferredTime) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    // Validate date is not in the past
    const selectedDate = new Date(preferredDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Reset time to start of day
    
    if (selectedDate < today) {
        showNotification('Please select a date today or in the future', 'warning');
        return;
    }
    
    // Get selected services
    const selectedServices = [];
    document.querySelectorAll('.service-checkbox:checked').forEach(checkbox => {
        selectedServices.push(parseInt(checkbox.dataset.serviceId));
    });
    
    if (selectedServices.length === 0) {
        showNotification('Please select at least one service', 'warning');
        return;
    }
    
    try {
        const token = localStorage.getItem('authToken');
        
        showNotification('Booking appointment...', 'info');
        
        const appointmentData = {
            action: 'book_appointment',
            petName: petName,
            petType: petType,
            petBreed: petBreed,
            petAge: formData.get('petAge'),
            petSize: formData.get('petSize'),
            preferredDate: preferredDate,
            preferredTime: preferredTime,
            preferredStaff: formData.get('preferredStaff'),
            services: selectedServices,
            specialInstructions: formData.get('specialInstructions')
        };
        
        const response = await fetch(`${API_BASE}appointments.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(appointmentData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Appointment booked successfully!', 'success');
            
            // Reset form
            form.reset();
            document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = false);
            updateAppointmentTotal();
            
            // Optionally switch to tracking section
            setTimeout(() => {
                showSection('tracking');
            }, 2000);
            
        } else {
            throw new Error(result.error || 'Failed to book appointment');
        }
        
    } catch (error) {
        console.error('Error booking appointment:', error);
        showNotification('Error booking appointment: ' + error.message, 'error');
    }
}

// Initialize appointments functionality
async function initializeAppointments() {
    // Set minimum date for appointment booking (today)
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.querySelector('input[name="preferredDate"]');
    if (dateInput) {
        dateInput.min = today;
    }
    
    // Load services
    await loadServicesFromDatabase();
    
    // Load user appointments
    await loadUserAppointments();
    
    // Add event listeners
    addAppointmentEventListeners();
}

// Load services from database
async function loadServicesFromDatabase() {
    try {
        console.log('Loading services from database...');
        const response = await fetch(API_BASE + 'services.php?action=get_services');
        const result = await response.json();
        
        console.log('Services response:', result);
        
        if (result.success) {
            servicesData = result.services;
            console.log('Services data loaded:', servicesData);
            renderServices();
        } else {
            throw new Error(result.error || 'Failed to load services');
        }
    } catch (error) {
        console.error('Error loading services:', error);
        showNotification('Failed to load services. Please refresh the page.', 'error');
        
        // Show error state in services container
        const container = document.getElementById('servicesContainer');
        if (container) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-red-600 font-medium">Failed to load services</p>
                    <button onclick="loadServicesFromDatabase()" class="mt-2 px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">
                        Try Again
                    </button>
                </div>
            `;
        }
    }
}

// Render services in the appointment form
function renderServices() {
    console.log('Rendering services...');
    console.log('Services data:', servicesData);
    console.log('Current pet size:', currentPetSize);
    console.log('Services container found:', !!document.getElementById('servicesContainer'));
    
    const container = document.getElementById('servicesContainer');
    if (!container) {
        console.error('Services container not found!');
        return;
    }
    
    // Clear the container first to prevent duplicates
    container.innerHTML = '';
    
    // Show services even without pet size, but with appropriate pricing display
    if (!currentPetSize) {
        // Show a notice about pet size selection
        container.innerHTML = `
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-amber-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-amber-700 font-medium">Please select your pet's size above to see accurate pricing</p>
                </div>
            </div>
        `;
    }
    
    let html = '';
    
    // Basic Services
    if (servicesData.basic && servicesData.basic.length > 0) {
        html += renderServiceCategory('basic', 'Basic Services', 'blue', servicesData.basic);
    }
    
    // Premium Services
    if (servicesData.premium && servicesData.premium.length > 0) {
        html += renderServiceCategory('premium', 'Premium Services', 'purple', servicesData.premium);
    }
    
    // Add-ons
    if (servicesData.addon && servicesData.addon.length > 0) {
        html += renderServiceCategory('addon', 'Add-ons', 'green', servicesData.addon);
    }
    
    // If we already have content (from the notice), append to it
    if (container.innerHTML.includes('amber-50')) {
        container.innerHTML += html;
    } else {
        container.innerHTML = html;
    }
    
    // Re-attach event listeners
    const checkboxes = document.querySelectorAll('.service-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.dataset.service === 'Full Grooming Package') {
                handleFullGroomingPackageToggle(this);
            } else if (['Basic Bath', 'Nail Trimming', 'Ear Cleaning'].includes(this.dataset.service)) {
                handleBasicServiceToggle();
            }
            updateServiceSelection();
        });
    });
}

// Render service category
function renderServiceCategory(categoryKey, categoryTitle, color, services) {
    const colorClasses = {
        blue: {
            gradient: 'from-blue-50 to-indigo-50',
            border: 'border-blue-200',
            iconBg: 'bg-blue-100',
            iconText: 'text-blue-600',
            titleText: 'text-blue-900',
            itemBorder: 'border-blue-200 hover:border-blue-300'
        },
        purple: {
            gradient: 'from-purple-50 to-pink-50',
            border: 'border-purple-200',
            iconBg: 'bg-purple-100',
            iconText: 'text-purple-600',
            titleText: 'text-purple-900',
            itemBorder: 'border-purple-200 hover:border-purple-300'
        },
        green: {
            gradient: 'from-green-50 to-emerald-50',
            border: 'border-green-200',
            iconBg: 'bg-green-100',
            iconText: 'text-green-600',
            titleText: 'text-green-900',
            itemBorder: 'border-green-200 hover:border-green-300'
        }
    };
    
    const colors = colorClasses[color];
    
    const icons = {
        basic: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>',
        premium: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>',
        addon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12z"></path>'
    };
    
    let html = `
        <div class="bg-gradient-to-r ${colors.gradient} border-2 ${colors.border} rounded-xl p-6 shadow-sm">
            <div class="flex items-center mb-4">
                <div class="inline-flex items-center justify-center w-10 h-10 ${colors.iconBg} rounded-full mr-3">
                    <svg class="w-6 h-6 ${colors.iconText}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${icons[categoryKey]}
                    </svg>
                </div>
                <h3 class="text-lg font-semibold ${colors.titleText}">${categoryTitle}</h3>
            </div>
            <div class="space-y-4">
    `;
    
    services.forEach(service => {
        console.log('Processing service:', service.name, 'Service data:', service);
        const price = getServicePrice(service);
        console.log('Calculated price for', service.name, ':', price);
        let priceDisplay = '';
        let isDisabled = false;
        
        if (service.is_size_based && currentPetSize && price > 0) {
            // Size-based service with selected size - show specific price
            priceDisplay = `₱${price.toFixed(2)}`;
            isDisabled = false;
            console.log(`${service.name}: Size-based with specific price - ${priceDisplay}`);
        } else if (service.is_size_based && !currentPetSize) {
            // Size-based service without selected size - show base price if available
            if (service.base_price && service.base_price > 0) {
                priceDisplay = `From ₱${service.base_price.toFixed(2)}`;
            } else {
                // Find the lowest price from available pricing
                const prices = Object.values(service.pricing || {});
                if (prices.length > 0) {
                    const minPrice = Math.min(...prices);
                    priceDisplay = `From ₱${minPrice.toFixed(2)}`;
                } else {
                    priceDisplay = 'Select pet size first';
                }
            }
            isDisabled = false; // Allow selection even without size
            console.log(`${service.name}: Size-based without size - ${priceDisplay}`);
        } else if (!service.is_size_based) {
            // Fixed price service - always show price and enable
            if (price > 0) {
                priceDisplay = `₱${price.toFixed(2)}`;
                isDisabled = false;
            } else if (service.base_price && service.base_price > 0) {
                priceDisplay = `₱${service.base_price.toFixed(2)}`;
                isDisabled = false;
            } else {
                priceDisplay = 'Price not available';
                isDisabled = true;
            }
            console.log(`${service.name}: Fixed price service - ${priceDisplay}`);
        } else {
            // Fallback case
            priceDisplay = 'Select pet size first';
            isDisabled = true;
            console.log(`${service.name}: Fallback case - ${priceDisplay}`);
        }
        
        html += `
            <label class="flex items-center p-4 bg-white/80 rounded-lg border ${colors.itemBorder} transition-all duration-200 cursor-pointer hover:shadow-md ${isDisabled ? 'opacity-60' : ''}">
                <input type="checkbox" class="service-checkbox w-5 h-5 text-gold-500 rounded" 
                       data-service-id="${service.id}"
                       data-service="${service.name}" 
                       data-price="${price}"
                       ${isDisabled ? 'disabled' : ''}>
                <div class="ml-4 flex-1 flex justify-between items-center">
                    <div>
                        <span class="font-medium text-gray-900">${service.name}</span>
                        <p class="text-sm text-gray-600">${service.description}</p>
                        ${service.is_size_based ? `<p class="text-xs text-gray-500 mt-1">Size-based pricing</p>` : ''}
                        ${service.is_size_based && !currentPetSize ? `<p class="text-xs text-amber-600 mt-1">Select pet size for accurate pricing</p>` : ''}
                    </div>
                    <span class="text-lg font-bold text-gold-600">${priceDisplay}</span>
                </div>
            </label>
        `;
    });
    
    html += `
            </div>
        </div>
    `;
    
    return html;
}

// Get service price based on pet size
function getServicePrice(service, petSize = null) {
    const size = petSize || currentPetSize;
    
    console.log('Getting price for service:', service.name, 'Size:', size, 'Service data:', service);
    
    // For size-based services, return specific size price if available
    if (service.is_size_based && size && service.pricing && service.pricing[size]) {
        console.log('Size-based service with specific price:', service.pricing[size]);
        return service.pricing[size];
    }
    
    // For non-size-based services, return any available price
    if (!service.is_size_based) {
        // Check if there's a fixed price in pricing object
        if (service.pricing && Object.keys(service.pricing).length > 0) {
            const price = Object.values(service.pricing)[0];
            console.log('Non-size-based service with pricing object:', price);
            return price; // Return first available price
        }
        // Fallback to base price
        if (service.base_price && service.base_price > 0) {
            console.log('Non-size-based service with base price:', service.base_price);
            return service.base_price;
        }
    }
    
    // Return 0 if no price available (will trigger "Select pet size first" message)
    console.log('No price available for service:', service.name);
    return 0;
}

// Handle full grooming package toggle
function handleFullGroomingPackageToggle(checkbox) {
    const isFullGroomingChecked = checkbox.checked;
    const basicServiceCheckboxes = document.querySelectorAll('.service-checkbox[data-service="Basic Bath"], .service-checkbox[data-service="Nail Trimming"], .service-checkbox[data-service="Ear Cleaning"]');
    
    basicServiceCheckboxes.forEach(basicCheckbox => {
        const label = basicCheckbox.closest('label');
        
        if (isFullGroomingChecked) {
            basicCheckbox.disabled = true;
            basicCheckbox.checked = false;
            label.classList.add('opacity-50', 'cursor-not-allowed');
            label.classList.remove('hover:shadow-md');
        } else {
            basicCheckbox.disabled = false;
            label.classList.remove('opacity-50', 'cursor-not-allowed');
            label.classList.add('hover:shadow-md');
        }
    });
    
    updateServiceSelection();
}

// Handle basic service toggle
function handleBasicServiceToggle() {
    const basicServices = ['Basic Bath', 'Nail Trimming', 'Ear Cleaning'];
    const checkedBasicServices = basicServices.filter(service => {
        const checkbox = document.querySelector(`.service-checkbox[data-service="${service}"]`);
        return checkbox && checkbox.checked;
    });
    
    const fullGroomingCheckbox = document.querySelector('.service-checkbox[data-service="Full Grooming Package"]');
    const fullGroomingLabel = fullGroomingCheckbox?.closest('label');
    
    if (checkedBasicServices.length > 0) {
        if (fullGroomingCheckbox) {
            fullGroomingCheckbox.disabled = true;
            fullGroomingCheckbox.checked = false;
            fullGroomingLabel.classList.add('opacity-50', 'cursor-not-allowed');
            fullGroomingLabel.classList.remove('hover:shadow-md');
        }
    } else {
        if (fullGroomingCheckbox) {
            fullGroomingCheckbox.disabled = false;
            fullGroomingLabel.classList.remove('opacity-50', 'cursor-not-allowed');
            fullGroomingLabel.classList.add('hover:shadow-md');
        }
    }
    
    updateServiceSelection();
}

// Update service selection and total
function updateServiceSelection() {
    selectedServices = [];
    totalAmount = 0;
    
    console.log('Updating service selection...');
    console.log('Current pet size:', currentPetSize);
    
    document.querySelectorAll('.service-checkbox:checked:not(:disabled)').forEach(checkbox => {
        const serviceId = parseInt(checkbox.dataset.serviceId);
        const price = parseFloat(checkbox.dataset.price);
        const serviceName = checkbox.dataset.service;
        
        console.log(`Selected service: ${serviceName}, ID: ${serviceId}, Price: ${price}`);
        
        selectedServices.push(serviceId);
        totalAmount += price;
    });
    
    console.log('Total selected services:', selectedServices);
    console.log('Total amount:', totalAmount);
    
    updateOrderSummary();
}

// Update order summary display
function updateOrderSummary() {
    const container = document.getElementById('selectedServices');
    const totalElement = document.getElementById('totalAmount');
    
    if (selectedServices.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <p class="text-gray-500 font-medium">No services selected</p>
                <p class="text-sm text-gray-400">Choose from the services above</p>
            </div>
        `;
    } else {
        let html = '';
        selectedServices.forEach(serviceId => {
            const checkbox = document.querySelector(`.service-checkbox[data-service-id="${serviceId}"]`);
            if (checkbox) {
                const serviceName = checkbox.dataset.service;
                const price = parseFloat(checkbox.dataset.price);
                html += `
                    <div class="flex justify-between items-center bg-white/80 rounded-lg p-3">
                        <span class="font-medium text-gray-900">${serviceName}</span>
                        <span class="font-bold text-gold-600">₱${price.toFixed(2)}</span>
                    </div>
                `;
            }
        });
        container.innerHTML = html;
    }
    
    if (totalElement) {
        totalElement.textContent = `₱${totalAmount.toFixed(2)}`;
    }
}

// Add appointment event listeners
function addAppointmentEventListeners() {
    // Pet size change listener
    const petSizeSelect = document.querySelector('select[name="petSize"]');
    if (petSizeSelect) {
        petSizeSelect.addEventListener('change', function() {
            console.log('Pet size changed to:', this.value);
            currentPetSize = this.value;
            renderServices();
            selectedServices = [];
            updateOrderSummary();
        });
    }
    
    // Pet type change listener for breed options
    const petTypeSelect = document.querySelector('select[name="petType"]');
    if (petTypeSelect) {
        petTypeSelect.addEventListener('change', function() {
            updateBreedOptions(this.value);
        });
    }
    
    // Form submission listener
    const appointmentForm = document.getElementById('appointmentForm');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', handleAppointmentSubmission);
    }
    
    // Add date validation to main appointment form
    const preferredDate = document.querySelector('input[name="preferredDate"]');
    if (preferredDate) {
        preferredDate.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            selectedDate.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                showNotification('Cannot select past dates. Please choose today or a future date.', 'error');
                this.value = today.toISOString().split('T')[0]; // Reset to today
            }
        });
    }
    
    // Edit form submission listener
    const editAppointmentForm = document.getElementById('editAppointmentForm');
    if (editAppointmentForm) {
        editAppointmentForm.addEventListener('submit', handleEditAppointmentSubmission);
    }
    
    // Edit form event listeners
    const editPetTypeSelect = document.getElementById('editPetType');
    if (editPetTypeSelect) {
        editPetTypeSelect.addEventListener('change', function() {
            updateEditBreedOptions(this.value);
        });
    }
    
    const editPetSizeSelect = document.getElementById('editPetSize');
    if (editPetSizeSelect) {
        editPetSizeSelect.addEventListener('change', function() {
            renderEditServices(Object.values(servicesData).flat());
            updateEditOrderSummary();
        });
    }
    
    // Add date validation to edit form
    const editPreferredDate = document.getElementById('editPreferredDate');
    if (editPreferredDate) {
        editPreferredDate.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            selectedDate.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                showNotification('Cannot select past dates. Please choose today or a future date.', 'error');
                this.value = today.toISOString().split('T')[0]; // Reset to today
            }
        });
    }
    
    // Add Full Grooming Package logic to edit form
    if (editPetTypeSelect) {
        editPetTypeSelect.addEventListener('change', function() {
            updateEditBreedOptions(this.value);
        });
    }
}

// Update breed options based on pet type
function updateBreedOptions(petType) {
    const breedSelect = document.querySelector('select[name="petBreed"]');
    if (!breedSelect) return;
    
    breedSelect.innerHTML = '<option value="">Select breed</option>';
    
    const breeds = {
        dog: ['Golden Retriever', 'Labrador Retriever', 'German Shepherd', 'Bulldog', 'Poodle', 'Beagle', 'Rottweiler', 'Yorkshire Terrier', 'Boxer', 'Dachshund', 'Other'],
        cat: ['Persian', 'Maine Coon', 'Siamese', 'Ragdoll', 'British Shorthair', 'Abyssinian', 'Russian Blue', 'Sphynx', 'Bengal', 'Other'],
        others: ['Rabbit', 'Hamster', 'Guinea Pig', 'Bird', 'Other']
    };
    
    if (breeds[petType]) {
        breeds[petType].forEach(breed => {
            const option = document.createElement('option');
            option.value = breed;
            option.textContent = breed;
            breedSelect.appendChild(option);
        });
    }
}

// Load user appointments
async function loadUserAppointments() {
    try {
        const token = localStorage.getItem('authToken');
        const url = currentAppointmentFilter === 'all' 
            ? `${API_BASE}appointments.php?action=get_user_appointments`
            : `${API_BASE}appointments.php?action=get_user_appointments&status=${currentAppointmentFilter}`;
            
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            renderAppointments(result.appointments);
        } else {
            throw new Error(result.error || 'Failed to load appointments');
        }
    } catch (error) {
        console.error('Error loading appointments:', error);
        showNotification('Failed to load appointments', 'error');
    }
}

// Render appointments
function renderAppointments(appointments) {
    const container = document.getElementById('appointmentsContainer');
    
    if (!appointments || appointments.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <p class="text-gray-500 text-lg">No appointments found</p>
                <p class="text-sm text-gray-400">Book your first appointment to get started</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    appointments.forEach(appointment => {
        const statusColors = {
            scheduled: 'bg-blue-100 text-blue-800',
            confirmed: 'bg-green-100 text-green-800',
            in_progress: 'bg-yellow-100 text-yellow-800',
            completed: 'bg-gray-100 text-gray-800',
            cancelled: 'bg-red-100 text-red-800',
            no_show: 'bg-red-100 text-red-800'
        };
        
        const statusColor = statusColors[appointment.status] || 'bg-gray-100 text-gray-800';
        
        html += `
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-4 shadow-sm">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">${appointment.pet_name}</h3>
                        <p class="text-sm text-gray-600">${appointment.appointment_date} at ${appointment.appointment_time}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-medium ${statusColor}">
                        ${appointment.status.replace('_', ' ').toUpperCase()}
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Services</p>
                        <p class="text-sm text-gray-900">${appointment.services.map(service => service.name).join(', ')}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Amount</p>
                        <p class="text-sm text-gray-900">₱${parseFloat(appointment.total_amount).toFixed(2)}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-600">Duration</p>
                        <p class="text-sm text-gray-900">${appointment.estimated_duration} minutes</p>
                    </div>
                </div>
                
                ${appointment.special_instructions ? `
                    <div class="mb-4">
                        <p class="text-sm font-medium text-gray-600">Special Instructions</p>
                        <p class="text-sm text-gray-900">${appointment.special_instructions}</p>
                    </div>
                ` : ''}
                
                <div class="flex justify-end space-x-2">
                    ${appointment.status === 'scheduled' ? `
                        <button onclick="editAppointment(${appointment.id})" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">
                            Edit
                        </button>
                        <button onclick="cancelAppointment(${appointment.id})" class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600">
                            Cancel
                        </button>
                    ` : ''}
                    ${appointment.status === 'completed' ? `
                        <button onclick="viewReceipt(${appointment.id})" class="px-3 py-1 bg-green-500 text-white rounded text-sm hover:bg-green-600">
                            View Receipt
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Filter appointments
function filterAppointments(status) {
    currentAppointmentFilter = status;
    
    // Update filter buttons
    document.querySelectorAll('[onclick^="filterAppointments"]').forEach(btn => {
        btn.classList.remove('bg-gold-500', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });
    
    const activeBtn = document.querySelector(`[onclick="filterAppointments('${status}')"]`);
    if (activeBtn) {
        activeBtn.classList.remove('bg-gray-200', 'text-gray-700');
        activeBtn.classList.add('bg-gold-500', 'text-white');
    }
    
    // Reload appointments with filter
    loadUserAppointments();
}

// Edit appointment
async function editAppointment(appointmentId) {
    try {
        const token = localStorage.getItem('authToken');
        const response = await fetch(`${API_BASE}appointments.php?action=get_appointment_details&appointment_id=${appointmentId}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            populateEditForm(result.appointment);
            document.getElementById('editAppointmentModal').classList.remove('hidden');
        } else {
            throw new Error(result.error || 'Failed to load appointment details');
        }
    } catch (error) {
        console.error('Error loading appointment details:', error);
        showNotification('Error loading appointment details: ' + error.message, 'error');
    }
}

// Populate edit form with appointment data
function populateEditForm(appointment) {
    console.log('Populating edit form with appointment data:', appointment);
    
    // Set appointment ID
    document.getElementById('editAppointmentId').value = appointment.id;
    
    // Populate pet information
    document.getElementById('editPetName').value = appointment.pet_name;
    document.getElementById('editPetType').value = appointment.pet_type;
    document.getElementById('editPetSize').value = appointment.pet_size;
    
    // Update breed options and set value
    updateEditBreedOptions(appointment.pet_type);
    setTimeout(() => {
        document.getElementById('editPetBreed').value = appointment.pet_breed;
    }, 100);
    
    // Populate appointment details
    document.getElementById('editPreferredDate').value = appointment.appointment_date;
    
    // Fix time format - convert from HH:MM:SS to HH:MM for the select element
    let timeValue = appointment.appointment_time;
    if (timeValue && timeValue.includes(':')) {
        timeValue = timeValue.substring(0, 5); // Take only HH:MM part
    }
    document.getElementById('editPreferredTime').value = timeValue;
    
    document.getElementById('editSpecialInstructions').value = appointment.special_instructions || '';
    
    // Set minimum date for edit form
    setEditMinimumDate();
    
    // Store selected services globally
    editSelectedServices = appointment.services.map(service => parseInt(service.id));
    console.log('Selected services for edit:', editSelectedServices);
    
    // Load services for edit form
    loadEditServices();
    
    // Display selected services in the modal
    const selectedServicesContainer = document.getElementById('editSelectedServicesContainer');
    if (selectedServicesContainer) {
        let html = '<h4 class="text-md font-semibold mb-2">Selected Services:</h4><ul class="list-disc pl-5">';
        appointment.services.forEach(service => {
            html += `<li>${service.name} - ₱${parseFloat(service.price).toFixed(2)}</li>`;
        });
        html += '</ul>';
        selectedServicesContainer.innerHTML = html;
    }
    
    // Update order summary
    updateEditOrderSummary();
}

// Update breed options for edit form
function updateEditBreedOptions(petType) {
    const breedSelect = document.getElementById('editPetBreed');
    if (!breedSelect) return;
    
    breedSelect.innerHTML = '<option value="">Select breed</option>';
    
    const breeds = {
        dog: ['Golden Retriever', 'Labrador Retriever', 'German Shepherd', 'Bulldog', 'Poodle', 'Beagle', 'Rottweiler', 'Yorkshire Terrier', 'Boxer', 'Dachshund', 'Other'],
        cat: ['Persian', 'Maine Coon', 'Siamese', 'Ragdoll', 'British Shorthair', 'Abyssinian', 'Russian Blue', 'Sphynx', 'Bengal', 'Other'],
        others: ['Rabbit', 'Hamster', 'Guinea Pig', 'Bird', 'Other']
    };
    
    if (breeds[petType]) {
        breeds[petType].forEach(breed => {
            const option = document.createElement('option');
            option.value = breed;
            option.textContent = breed;
            breedSelect.appendChild(option);
        });
    }
}

// Set minimum date for edit form
function setEditMinimumDate() {
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('editPreferredDate');
    if (dateInput) {
        dateInput.min = today;
        console.log('Set minimum date for edit form:', today);
    }
}

// Load services for edit form
async function loadEditServices() {
    try {
        console.log('Loading services for edit form...');
        const response = await fetch(`${API_BASE}services.php?action=get_services`);
        const result = await response.json();
        
        console.log('Services API response:', result);
        
        if (result.success) {
            servicesData = result.services; // Store services data globally
            console.log('Services data for edit:', servicesData);
            renderEditServices(result.services);
        } else {
            throw new Error(result.error || 'Failed to load services');
        }
    } catch (error) {
        console.error('Error loading services for edit:', error);
        showNotification('Failed to load services', 'error');
    }
}

// Render services for edit form
function renderEditServices(services) {
    console.log('Rendering edit services with data:', services);
    const container = document.getElementById('editServicesContainer');
    if (!container) {
        console.error('Edit services container not found!');
        return;
    }
    
    container.innerHTML = '';
    
    if (!services || typeof services !== 'object') {
        console.log('No services data or invalid format');
        container.innerHTML = '<p class="text-center text-gray-500">No services available</p>';
        return;
    }
    
    // Services is an object with categories, not an array
    const basicServices = services.basic || [];
    const premiumServices = services.premium || [];
    const addonServices = services.addon || [];
    
    console.log('Service categories:', {
        basic: basicServices.length,
        premium: premiumServices.length,
        addon: addonServices.length
    });
    
    // Render each category
    if (basicServices.length > 0) {
        container.innerHTML += renderEditServiceCategory('Basic Services', basicServices, 'blue');
    }
    if (premiumServices.length > 0) {
        container.innerHTML += renderEditServiceCategory('Premium Services', premiumServices, 'purple');
    }
    if (addonServices.length > 0) {
        container.innerHTML += renderEditServiceCategory('Add-ons', addonServices, 'green');
    }
    
    // Add event listeners
    addEditServiceEventListeners();
}

// Render service category for edit form
function renderEditServiceCategory(title, services, color) {
    const colorClasses = {
        blue: 'from-blue-50 to-indigo-50 border-blue-200',
        purple: 'from-purple-50 to-violet-50 border-purple-200',
        green: 'from-green-50 to-emerald-50 border-green-200'
    };
    
    const colors = colorClasses[color];
    
    let html = `
        <div class="bg-gradient-to-r ${colors} border rounded-xl p-6">
            <h4 class="text-lg font-semibold mb-4 text-gray-900">${title}</h4>
            <div class="space-y-4">
    `;
    
    services.forEach(service => {
        const isChecked = editSelectedServices.includes(service.id);
        const editPetSize = document.getElementById('editPetSize').value;
        const price = getServicePrice(service, editPetSize);
        let priceDisplay = '';
        let isDisabled = false;
        
        if (service.is_size_based && editPetSize && price > 0) {
            // Size-based service with selected size - show specific price
            priceDisplay = `₱${price.toFixed(2)}`;
            isDisabled = false;
        } else if (service.is_size_based && !editPetSize) {
            // Size-based service without selected size - show base price if available
            if (service.base_price && service.base_price > 0) {
                priceDisplay = `From ₱${service.base_price.toFixed(2)}`;
            } else {
                // Find the lowest price from available pricing
                const prices = Object.values(service.pricing || {});
                if (prices.length > 0) {
                    const minPrice = Math.min(...prices);
                    priceDisplay = `From ₱${minPrice.toFixed(2)}`;
                } else {
                    priceDisplay = 'Select pet size first';
                }
            }
            isDisabled = false; // Allow selection even without size
        } else if (!service.is_size_based) {
            // Fixed price service - always show price and enable
            if (price > 0) {
                priceDisplay = `₱${price.toFixed(2)}`;
                isDisabled = false;
            } else if (service.base_price && service.base_price > 0) {
                priceDisplay = `₱${service.base_price.toFixed(2)}`;
                isDisabled = false;
            } else {
                priceDisplay = 'Price not available';
                isDisabled = true;
            }
        } else {
            // Fallback case
            priceDisplay = 'Select pet size first';
            isDisabled = true;
        }
        
        html += `
            <label class="flex items-center space-x-3 p-3 bg-white rounded-lg border border-gray-200 hover:border-gold-300 transition-colors duration-200 cursor-pointer ${isDisabled ? 'opacity-60' : ''}">
                <input type="checkbox" 
                       class="w-4 h-4 text-gold-600 border-gray-300 rounded focus:ring-gold-500 service-checkbox" 
                       data-service-id="${service.id}"
                       data-service="${service.name}"
                       data-price="${price}"
                       ${isChecked ? 'checked' : ''}
                       ${isDisabled ? 'disabled' : ''}>
                <div class="flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-900">${service.name}</p>
                            <p class="text-sm text-gray-600">${service.description}</p>
                            ${service.is_size_based ? `<p class="text-xs text-gray-500 mt-1">Size-based pricing</p>` : ''}
                            ${service.is_size_based && !editPetSize ? `<p class="text-xs text-amber-600 mt-1">Select pet size for accurate pricing</p>` : ''}
                        </div>
                        <span class="text-sm font-semibold text-gold-600">${priceDisplay}</span>
                    </div>
                </div>
            </label>
        `;
    });
    
    html += `
            </div>
        </div>
    `;
    
    return html;
}

// Add event listeners for edit service checkboxes
function addEditServiceEventListeners() {
    const checkboxes = document.querySelectorAll('#editServicesContainer input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const serviceId = parseInt(this.dataset.serviceId);
            
            if (this.checked) {
                if (!editSelectedServices.includes(serviceId)) {
                    editSelectedServices.push(serviceId);
                }
            } else {
                editSelectedServices = editSelectedServices.filter(id => id !== serviceId);
            }
            
            // Handle Full Grooming Package logic
            if (this.dataset.service === 'Full Grooming Package') {
                handleEditFullGroomingPackageToggle(this);
            } else if (['Basic Bath', 'Nail Trimming', 'Ear Cleaning'].includes(this.dataset.service)) {
                handleEditBasicServiceToggle();
            }
            
            updateEditOrderSummary();
        });
    });
}

// Handle Full Grooming Package toggle for edit form
function handleEditFullGroomingPackageToggle(checkbox) {
    const isFullGroomingChecked = checkbox.checked;
    const basicServiceCheckboxes = document.querySelectorAll('#editServicesContainer .service-checkbox[data-service="Basic Bath"], #editServicesContainer .service-checkbox[data-service="Nail Trimming"], #editServicesContainer .service-checkbox[data-service="Ear Cleaning"]');
    
    basicServiceCheckboxes.forEach(basicCheckbox => {
        const label = basicCheckbox.closest('label');
        
        if (isFullGroomingChecked) {
            // Disable basic services
            basicCheckbox.disabled = true;
            basicCheckbox.checked = false;
            label.classList.add('opacity-50', 'cursor-not-allowed');
            label.classList.remove('hover:shadow-md');
        } else {
            // Enable basic services
            basicCheckbox.disabled = false;
            label.classList.remove('opacity-50', 'cursor-not-allowed');
            label.classList.add('hover:shadow-md');
        }
    });
}

// Handle basic service toggle for edit form
function handleEditBasicServiceToggle() {
    const basicServices = ['Basic Bath', 'Nail Trimming', 'Ear Cleaning'];
    const checkedBasicServices = basicServices.filter(service => {
        const checkbox = document.querySelector(`#editServicesContainer .service-checkbox[data-service="${service}"]`);
        return checkbox && checkbox.checked;
    });
    
    const fullGroomingCheckbox = document.querySelector('#editServicesContainer .service-checkbox[data-service="Full Grooming Package"]');
    const fullGroomingLabel = fullGroomingCheckbox?.closest('label');
    
    if (checkedBasicServices.length > 0) {
        // Disable Full Grooming Package
        if (fullGroomingCheckbox) {
            fullGroomingCheckbox.disabled = true;
            fullGroomingCheckbox.checked = false;
            fullGroomingLabel.classList.add('opacity-50', 'cursor-not-allowed');
            fullGroomingLabel.classList.remove('hover:shadow-md');
        }
    } else {
        // Enable Full Grooming Package
        if (fullGroomingCheckbox) {
            fullGroomingCheckbox.disabled = false;
            fullGroomingLabel.classList.remove('opacity-50', 'cursor-not-allowed');
            fullGroomingLabel.classList.add('hover:shadow-md');
        }
    }
}

// Update edit order summary
function updateEditOrderSummary() {
    const container = document.getElementById('editSelectedServices');
    const totalElement = document.getElementById('editTotalAmount');
    
    if (editSelectedServices.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <p class="text-gray-500 font-medium">No services selected</p>
                <p class="text-sm text-gray-400">Choose from the services above</p>
            </div>
        `;
        totalElement.textContent = '₱0';
        return;
    }
    
    let total = 0;
    let html = '';
    
    editSelectedServices.forEach(serviceId => {
        const service = Object.values(servicesData).flat().find(s => s.id === serviceId);
        if (service) {
            const price = getServicePrice(service, document.getElementById('editPetSize').value);
            if (price) {
                total += parseFloat(price);
                html += `
                    <div class="flex justify-between items-center p-3 bg-white rounded-lg border border-gray-100">
                        <div>
                            <p class="font-medium text-gray-900">${service.name}</p>
                            <p class="text-sm text-gray-600">${service.description}</p>
                        </div>
                        <span class="font-semibold text-gold-600">₱${parseFloat(price).toFixed(2)}</span>
                    </div>
                `;
            }
        }
    });
    
    container.innerHTML = html;
    totalElement.textContent = `₱${total.toFixed(2)}`;
}

// Cancel appointment
async function cancelAppointment(appointmentId) {
    // Create and show a confirmation modal instead of using alert
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
    modal.id = 'cancelConfirmModal';
    
    modal.innerHTML = `
        <div class="bg-white rounded-lg p-6 max-w-md mx-auto shadow-xl">
            <div class="flex items-center mb-4">
                <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <h3 class="ml-3 text-lg font-medium text-gray-900">Cancel Appointment</h3>
            </div>
            <p class="text-gray-600 mb-6">Are you sure you want to cancel this appointment? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <button id="cancelNo" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition-colors">No, Keep It</button>
                <button id="cancelYes" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition-colors">Yes, Cancel</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Handle modal button clicks
    return new Promise((resolve) => {
        document.getElementById('cancelNo').addEventListener('click', () => {
            document.body.removeChild(modal);
            resolve(false);
        });
        
        document.getElementById('cancelYes').addEventListener('click', () => {
            document.body.removeChild(modal);
            resolve(true);
        });
    }).then(async (confirmed) => {
        if (!confirmed) {
            return;
        }
        
        try {
            const token = localStorage.getItem('authToken');
            const response = await fetch(`${API_BASE}appointments.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    action: 'cancel_appointment',
                    appointment_id: appointmentId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification('Appointment cancelled successfully', 'success');
                loadUserAppointments();
            } else {
                throw new Error(result.error || 'Failed to cancel appointment');
            }
        } catch (error) {
            console.error('Error cancelling appointment:', error);
            showNotification('Error cancelling appointment: ' + error.message, 'error');
        }
    })
}

// Handle edit appointment form submission
async function handleEditAppointmentSubmission(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const appointmentId = formData.get('appointmentId');
    
    // Collect selected services
    const selectedServices = [];
    document.querySelectorAll('#editServicesContainer input[type="checkbox"]:checked').forEach(checkbox => {
        selectedServices.push(parseInt(checkbox.dataset.serviceId));
    });
    
    if (selectedServices.length === 0) {
        showNotification('Please select at least one service', 'warning');
        return;
    }
    
    // Client-side date validation
    const appointmentDate = formData.get('preferredDate');
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Set to start of day
    const selectedDate = new Date(appointmentDate);
    selectedDate.setHours(0, 0, 0, 0); // Set to start of day
    
    console.log('Date validation:', {
        appointmentDate: appointmentDate,
        today: today.toISOString().split('T')[0],
        selectedDate: selectedDate.toISOString().split('T')[0],
        isPast: selectedDate < today
    });
    
    if (selectedDate < today) {
        showNotification('Appointment date cannot be in the past. Please select today or a future date.', 'error');
        return;
    }
    
    try {
        const token = localStorage.getItem('authToken');
        const response = await fetch(`${API_BASE}appointments.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                action: 'update_appointment',
                appointment_id: appointmentId,
                pet_name: formData.get('petName'),
                pet_type: formData.get('petType'),
                pet_breed: formData.get('petBreed'),
                pet_size: formData.get('petSize'),
                appointment_date: formData.get('preferredDate'),
                appointment_time: formData.get('preferredTime'),
                special_instructions: formData.get('specialInstructions'),
                selected_services: selectedServices
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Appointment updated successfully', 'success');
            closeEditModal();
            loadUserAppointments();
        } else {
            throw new Error(result.error || 'Failed to update appointment');
        }
    } catch (error) {
        console.error('Error updating appointment:', error);
        showNotification('Error updating appointment: ' + error.message, 'error');
    }
}

// View receipt
function viewReceipt(appointmentId) {
    // Implementation for viewing receipt
    showNotification('Receipt functionality coming soon', 'info');
}

// Removed duplicate DOMContentLoaded event listener