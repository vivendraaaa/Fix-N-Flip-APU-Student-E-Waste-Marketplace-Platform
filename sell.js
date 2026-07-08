// Sell Page JavaScript - Form Management and Price Calculation

// File arrays for sell page
let sellImageFiles = [];
let sellVideoFile = null;
const MAX_VIDEO_SIZE_BYTES = 40 * 1024 * 1024; // 40MB
const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024; // 10MB per image

// Scroll to sell form smoothly
function scrollToForm() {
    document.getElementById('sell-form').scrollIntoView({ behavior: 'smooth' });
}

// Multi-step wizard navigation
let currentStep = 1;

function goToStep(step) {
    // Validate current step before proceeding
    if (step > currentStep && !validateStep(currentStep)) {
        return;
    }

    // Hide all steps
    document.querySelectorAll('.wizard-step').forEach(el => {
        el.classList.remove('active');
    });

    // Show target step
    document.querySelector(`.wizard-step[data-step="${step}"]`).classList.add('active');

    // Update progress bar
    document.querySelectorAll('.progress-step').forEach(el => {
        const stepNum = parseInt(el.dataset.step);
        if (stepNum <= step) {
            el.classList.add('active');
            if (stepNum < step) {
                el.classList.add('completed');
            }
        } else {
            el.classList.remove('active', 'completed');
        }
    });

    currentStep = step;

    // Trigger step-specific actions
    if (step === 2) {
        updateBrands();
    } else if (step === 3) {
        updateQuestions();
    } else if (step === 4) {
        updateQuotationSummary();
    }
}

function validateStep(step) {
    if (step === 1) {
        const deviceType = document.getElementById('device_type').value;
        if (!deviceType) {
            showToast('Please select a device type');
            return false;
        }
    } else if (step === 2) {
        const brand = document.getElementById('brand').value;
        const modelDropdown = document.getElementById('model');
        const modelText = document.getElementById('model_text');

        // Get model value (dropdown or text input)
        let modelValue = '';
        if (modelDropdown.value === 'other') {
            modelValue = modelText.value;
        } else if (modelText.style.display === 'block') {
            modelValue = modelText.value;
        } else {
            modelValue = modelDropdown.value;
        }

        const storageDropdown = document.getElementById('storage');
        const storageCustom = document.getElementById('storage_custom');
        let storageValue = storageDropdown.value;
        if (storageDropdown.value === 'other') {
            storageValue = storageCustom.value;
        }

        const ramDropdown = document.getElementById('ram');
        const ramCustom = document.getElementById('ram_custom');
        let ramValue = ramDropdown.value;
        if (ramDropdown.value === 'other') {
            ramValue = ramCustom.value;
        }

        const batteryHealth = document.getElementById('battery_health').value;
        const accessories = document.getElementById('accessories').value;

        if (!brand || !modelValue || !storageValue || !ramValue || !batteryHealth || !accessories) {
            showToast('Please fill in all required fields');
            return false;
        }
    } else if (step === 3) {
        const deviceType = document.getElementById('device_type').value;
        const questions = deviceQuestions[deviceType] || [];
        for (const question of questions) {
            const answered = document.querySelector(`input[name="${question.id}"]:checked`);
            if (!answered) {
                showToast('Please answer all questions about your device condition');
                return false;
            }
        }
    }

    return true;
}

// Device type card selection
document.addEventListener('DOMContentLoaded', function() {
    // Only attach device type handlers if user is logged in
    if (typeof isLoggedIn !== 'undefined' && isLoggedIn) {
        const deviceTypeCards = document.querySelectorAll('.device-type-card');
        const deviceTypeInput = document.getElementById('device_type');

        deviceTypeCards.forEach(card => {
            card.addEventListener('click', function() {
                const deviceType = this.dataset.type;
                console.log('Selected device type:', deviceType);

                if (deviceTypeInput) {
                    deviceTypeInput.value = deviceType;
                    console.log('device_type input value set to:', deviceTypeInput.value);
                } else {
                    console.error('device_type input not found!');
                }

                // Remove active class from all cards
                deviceTypeCards.forEach(c => c.classList.remove('active'));
                // Add active class to selected card
                this.classList.add('active');

                // Auto-advance to step 2 after short delay
                setTimeout(() => {
                    goToStep(2);
                }, 300);
            });
        });
    }
});

// Update quotation summary
function updateQuotationSummary() {
    const deviceType = document.getElementById('device_type').value;
    const brand = document.getElementById('brand').value;
    const modelDropdown = document.getElementById('model');
    const modelText = document.getElementById('model_text');
    const storageDropdown = document.getElementById('storage');
    const storageCustom = document.getElementById('storage_custom');
    let storageValue = storageDropdown.value;
    if (storageDropdown.value === 'other') {
        storageValue = storageCustom.value;
    }
    const ramDropdown = document.getElementById('ram');
    const ramCustom = document.getElementById('ram_custom');
    let ramValue = ramDropdown.value;
    if (ramDropdown.value === 'other') {
        ramValue = ramCustom.value;
    }

    // Get model value (dropdown or text input)
    let modelValue = '';
    if (modelDropdown.value === 'other') {
        modelValue = modelText.value;
    } else if (modelText.style.display === 'block') {
        modelValue = modelText.value;
    } else {
        modelValue = modelDropdown.value;
    }

    document.getElementById('quote-device-type').textContent = deviceType || '-';
    document.getElementById('quote-brand').textContent = brand || '-';
    document.getElementById('quote-model').textContent = modelValue || '-';
    document.getElementById('quote-storage').textContent = storageValue ? storageValue + ' GB' : '-';
    document.getElementById('quote-ram').textContent = ramValue ? ramValue + ' GB' : '-';
}

// Comprehensive device model database
const deviceModels = {
    'Smartphone': {
        'Apple': [
            'iPhone 16 Pro Max', 'iPhone 16 Pro', 'iPhone 16 Plus', 'iPhone 16',
            'iPhone 15 Pro Max', 'iPhone 15 Pro', 'iPhone 15 Plus', 'iPhone 15',
            'iPhone 14 Pro Max', 'iPhone 14 Pro', 'iPhone 14 Plus', 'iPhone 14',
            'iPhone 13 Pro Max', 'iPhone 13 Pro', 'iPhone 13', 'iPhone 13 Mini',
            'iPhone 12 Pro Max', 'iPhone 12 Pro', 'iPhone 12', 'iPhone 12 Mini',
            'iPhone 11 Pro Max', 'iPhone 11 Pro', 'iPhone 11',
            'iPhone SE 3', 'iPhone SE 2'
        ],
        'Samsung': [
            'Galaxy S24 Ultra', 'Galaxy S24 Plus', 'Galaxy S24',
            'Galaxy S23 Ultra', 'Galaxy S23 Plus', 'Galaxy S23',
            'Galaxy S22 Ultra', 'Galaxy S22 Plus', 'Galaxy S22',
            'Galaxy S21 Ultra', 'Galaxy S21 Plus', 'Galaxy S21',
            'Galaxy S20 Ultra', 'Galaxy S20 Plus', 'Galaxy S20',
            'Galaxy Note 20 Ultra', 'Galaxy Note 20',
            'Galaxy A54', 'Galaxy A34', 'Galaxy A24'
        ],
        'Google': [
            'Pixel 8 Pro', 'Pixel 8', 'Pixel 7a', 'Pixel 7 Pro', 'Pixel 7',
            'Pixel 6 Pro', 'Pixel 6', 'Pixel 6a'
        ],
        'OnePlus': [
            'OnePlus 12', 'OnePlus 11', 'OnePlus 10', 'OnePlus 9', 'OnePlus 8',
            'OnePlus Nord 3', 'OnePlus Nord 2', 'OnePlus Nord CE 3'
        ],
        'Xiaomi': [
            'Xiaomi 14 Ultra', 'Xiaomi 14', 'Xiaomi 13 Ultra', 'Xiaomi 13',
            'Redmi Note 13 Pro', 'Redmi Note 13', 'Redmi Note 12'
        ],
        'Honor': [
            'Honor Magic 6 Pro', 'Honor Magic 6', 'Honor 90', 'Honor 70'
        ],
        'Huawei': [
            'Huawei P60 Pro', 'Huawei P60', 'Huawei Mate 50'
        ],
        'Oppo': [
            'Oppo Find X7 Ultra', 'Oppo Find X7', 'Oppo Reno 10', 'Oppo Reno 9'
        ]
    },
    'Tablet': {
        'Apple': [
            'iPad Pro 12.9 (M4)', 'iPad Pro 11 (M4)', 'iPad Pro 12.9 (M2)', 'iPad Pro 11 (M2)',
            'iPad Air 6', 'iPad Air 5', 'iPad 10', 'iPad 9',
            'iPad Mini 6', 'iPad Mini 5'
        ],
        'Samsung': [
            'Galaxy Tab S9 Ultra', 'Galaxy Tab S9 Plus', 'Galaxy Tab S9',
            'Galaxy Tab S8 Ultra', 'Galaxy Tab S8 Plus', 'Galaxy Tab S8',
            'Galaxy Tab A9'
        ]
    },
    'Laptop': {
        'Apple': [],
        'Dell': [],
        'HP': [],
        'Lenovo': [],
        'Asus': [],
        'Acer': [],
        'MSI': []
    },
    'Smartwatch': {
        'Apple': [
            'Apple Watch Ultra 2', 'Apple Watch Ultra',
            'Apple Watch Series 9', 'Apple Watch Series 8', 'Apple Watch Series 7',
            'Apple Watch SE 2', 'Apple Watch SE'
        ],
        'Samsung': [
            'Galaxy Watch 6', 'Galaxy Watch 5', 'Galaxy Watch 4'
        ],
        'Garmin': [
            'Garmin Fenix 7', 'Garmin Forerunner 965', 'Garmin Venu 3'
        ],
        'Fitbit': [
            'Fitbit Sense 2', 'Fitbit Versa 4', 'Fitbit Charge 6'
        ]
    }
};

// Questions for each device type - all Yes/No format (7 questions each)
const deviceQuestions = {
    'Smartphone': [
        {
            id: 'question_locks',
            text: 'Is your device free of any locks? (Passcode, Find My, Apple ID/Google Account, Remote Management Lock)',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_screen',
            text: 'What is your device LCD & screen condition?',
            options: [
                { value: 'flawless', label: 'Flawless' },
                { value: 'minor_scratches', label: '2-3 Minor Scratches' },
                { value: 'heavy_scratches', label: 'Heavy Scratches' },
                { value: 'cracked', label: 'Cracked' },
                { value: 'not_working', label: 'Not working (LCD lines, burns, shadow, dead pixels, unresponsive)' }
            ]
        },
        {
            id: 'question_body',
            text: 'What is your device body condition (back and side)?',
            options: [
                { value: 'flawless', label: 'Flawless' },
                { value: 'minor_scratches', label: '2-3 Minor Scratches' },
                { value: 'heavy_scratches', label: 'Heavy Scratches' },
                { value: 'dented', label: 'Dented' },
                { value: 'cracked', label: 'Cracked' }
            ]
        },
        {
            id: 'question_biometric',
            text: 'Is your device Fingerprint/Face ID working?',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_functions',
            text: 'Are all the device functions below working fine? (Speakers, Microphone, Buttons, WiFi, Bluetooth)',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_cameras',
            text: 'Are both the front and back cameras of your device working as intended?',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_issues',
            text: 'Does your device have any of these issue(s)?',
            options: [
                { value: 'bloat_screen_body', label: 'Bloated Battery/Screen or Body Pop Out' },
                { value: 'liquid_damage', label: 'Liquid Damage' },
                { value: 'cannot_turn_on', label: 'Device cannot turn on/datawipe' },
                { value: 'non_genuine_parts', label: 'Non-Genuine Parts/Missing Parts' },
                { value: 'jailbroken', label: 'Jailbroken or rooted devices' },
                { value: 'none', label: 'None of the above' }
            ]
        }
    ],
    'Tablet': [
        {
            id: 'question_locks',
            text: 'Is your device free of any locks? (Passcode, Find My, Apple ID/Google Account, Remote Management Lock)',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_screen',
            text: 'What is your device LCD & screen condition?',
            options: [
                { value: 'flawless', label: 'Flawless' },
                { value: 'minor_scratches', label: '2-3 Minor Scratches' },
                { value: 'heavy_scratches', label: 'Heavy Scratches' },
                { value: 'cracked', label: 'Cracked' },
                { value: 'not_working', label: 'Not working (LCD lines, burns, shadow, dead pixels, unresponsive)' }
            ]
        },
        {
            id: 'question_body',
            text: 'What is your device body condition (back and side)?',
            options: [
                { value: 'flawless', label: 'Flawless' },
                { value: 'minor_scratches', label: '2-3 Minor Scratches' },
                { value: 'heavy_scratches', label: 'Heavy Scratches' },
                { value: 'dented', label: 'Dented' },
                { value: 'cracked', label: 'Cracked' }
            ]
        },
        {
            id: 'question_biometric',
            text: 'Is your device Fingerprint/Face ID working?',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_functions',
            text: 'Are all the device functions below working fine? (Speakers, Microphone, Buttons, WiFi, Bluetooth)',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_cameras',
            text: 'Are both the front and back cameras of your device working as intended?',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_issues',
            text: 'Does your device have any of these issue(s)?',
            options: [
                { value: 'bloat_screen_body', label: 'Bloated Battery/Screen or Body Pop Out' },
                { value: 'liquid_damage', label: 'Liquid Damage' },
                { value: 'cannot_turn_on', label: 'Device cannot turn on/datawipe' },
                { value: 'non_genuine_parts', label: 'Non-Genuine Parts/Missing Parts' },
                { value: 'jailbroken', label: 'Jailbroken or rooted devices' },
                { value: 'none', label: 'None of the above' }
            ]
        }
    ],
    'Laptop': [
        {
            id: 'question_locks',
            text: 'Is your device free of any locks? (Passcode, Find My, Apple ID/Google Account, Remote Management Lock)',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_screen',
            text: 'What is your device LCD & screen condition',
            options: [
                { value: 'flawless', label: 'Flawless' },
                { value: 'minor_scratches', label: '2-3 Minor Scratches' },
                { value: 'heavy_scratches', label: 'Heavy Scratches' },
                { value: 'cracked', label: 'Cracked' },
                { value: 'not_working', label: 'Not working (LCD lines, burns, shadow, dead pixels, unresponsive)' }
            ]
        },
        {
            id: 'question_body',
            text: 'What is your device body condition (back and side, keyboard)?',
            options: [
                { value: 'flawless', label: 'Flawless' },
                { value: 'scratches', label: 'Scratches' },
                { value: 'dented_bent', label: 'Dented/Bent corner' },
                { value: 'cracked', label: 'Cracked' }
            ]
        },
        {
            id: 'question_keyboard',
            text: 'Is your device\'s keyboard, Buttons, Touch bar and Trackpad functioning?',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_camera',
            text: 'Is your device\'s front camera working fine as intended?',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_issues',
            text: 'Does your device have any of these issue(s)?',
            options: [
                { value: 'bloat_screen_body', label: 'Bloated Battery/Screen or Body Pop Out' },
                { value: 'liquid_damage', label: 'Liquid Damage' },
                { value: 'cannot_turn_on', label: 'Device cannot turn on/datawipe' },
                { value: 'non_genuine_parts', label: 'Non-Genuine Parts/Missing Parts' },
                { value: 'jailbroken', label: 'Jailbroken or rooted devices' },
                { value: 'none', label: 'None of the above' }
            ]
        }
    ],
    'Smartwatch': [
        {
            id: 'question_locks',
            text: 'Is your device free of any locks? (Passcode, Find My, Apple ID/Google Account, Remote Management Lock)',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_screen',
            text: 'What is your device LCD & screen condition',
            options: [
                { value: 'flawless', label: 'Flawless' },
                { value: 'minor_scratches', label: '2-3 Minor Scratches' },
                { value: 'heavy_scratches', label: 'Heavy Scratches' },
                { value: 'cracked', label: 'Cracked' },
                { value: 'not_working', label: 'Not working (LCD lines, burns, shadow, dead pixels, unresponsive)' }
            ]
        },
        {
            id: 'question_body',
            text: 'What is your device body condition (back and side)?',
            options: [
                { value: 'flawless', label: 'Flawless' },
                { value: 'minor_scratches', label: '2-3 Minor Scratches' },
                { value: 'heavy_scratches', label: 'Heavy Scratches' },
                { value: 'dented', label: 'Dented' },
                { value: 'cracked', label: 'Cracked' }
            ]
        },
        {
            id: 'question_buttons',
            text: 'Are the buttons and sensors functioning?',
            options: [
                { value: 'yes', label: 'Yes' },
                { value: 'no', label: 'No' }
            ]
        },
        {
            id: 'question_issues',
            text: 'Does your device have any of these issue(s)?',
            options: [
                { value: 'bloat_screen_body', label: 'Bloated Battery/Screen or Body Pop Out' },
                { value: 'liquid_damage', label: 'Liquid Damage' },
                { value: 'cannot_turn_on', label: 'Device cannot turn on/datawipe' },
                { value: 'non_genuine_parts', label: 'Non-Genuine Parts/Missing Parts' },
                { value: 'jailbroken', label: 'Jailbroken or rooted devices' },
                { value: 'none', label: 'None of the above' }
            ]
        }
    ]
};

// Flag to prevent dialog from reopening when brand is set programmatically
let isSettingBrandProgrammatically = false;

// Update brand dropdown based on device type
function updateBrands() {
    const deviceType = document.getElementById('device_type').value;
    const brandDropdown = document.getElementById('brand');
    const modelDropdown = document.getElementById('model');
    const modelInput = document.getElementById('model_text');
    
    brandDropdown.innerHTML = '<option value="">Select Brand</option>';
    modelDropdown.innerHTML = '<option value="">Select Model</option>';
    
    if (!deviceType || !deviceModels[deviceType]) {
        return;
    }
    
    const brands = Object.keys(deviceModels[deviceType]);
    brands.forEach(brand => {
        const option = document.createElement('option');
        option.value = brand;
        option.textContent = brand;
        brandDropdown.appendChild(option);
    });

    // Add "Other" option to brand dropdown
    const otherOption = document.createElement('option');
    otherOption.value = 'other';
    otherOption.textContent = 'Other (Custom Device)';
    brandDropdown.appendChild(otherOption);
    
    // Show/hide model dropdown vs text input based on device type
    if (deviceType === 'Laptop') {
        modelDropdown.style.display = 'none';
        modelInput.style.display = 'block';
        modelInput.required = true;
    } else {
        modelDropdown.style.display = 'block';
        modelInput.style.display = 'none';
        modelInput.required = false;
        modelDropdown.required = true;
    }
}

// Add event listener to brand dropdown for "Other" option (call once on page load)
document.addEventListener('DOMContentLoaded', function() {
    const brandDropdown = document.getElementById('brand');
    if (brandDropdown) {
        brandDropdown.addEventListener('change', function() {
            if (this.value === 'other' && !isSettingBrandProgrammatically) {
                openCustomDialog();
            }
        });
    }
});

// Update model dropdown based on selected brand
function updateModels() {
    const deviceType = document.getElementById('device_type').value;
    const brand = document.getElementById('brand').value;
    const modelDropdown = document.getElementById('model');
    const modelText = document.getElementById('model_text');
    
    modelDropdown.innerHTML = '<option value="">Select Model</option>';
    
    // For laptops, always show text input instead of dropdown
    if (deviceType === 'Laptop') {
        modelDropdown.style.display = 'none';
        modelDropdown.required = false;
        modelText.style.display = 'block';
        modelText.required = true;
        modelText.placeholder = 'Enter laptop model (e.g., MacBook Pro 14, Dell XPS 13)';
        return;
    }
    
    // For other devices, show dropdown
    modelText.style.display = 'none';
    modelText.required = false;
    modelDropdown.style.display = 'block';
    modelDropdown.required = true;
    
    if (!deviceType || !brand || !deviceModels[deviceType] || !deviceModels[deviceType][brand]) {
        return;
    }
    
    const models = deviceModels[deviceType][brand];
    models.forEach(model => {
        const option = document.createElement('option');
        option.value = model;
        option.textContent = model;
        modelDropdown.appendChild(option);
    });

    // Add "Other" option to model dropdown
    const otherOption = document.createElement('option');
    otherOption.value = 'other';
    otherOption.textContent = 'Other (Custom Model)';
    modelDropdown.appendChild(otherOption);
    
    // Add event listener to show/hide model text input
    modelDropdown.addEventListener('change', function() {
        if (this.value === 'other') {
            modelText.style.display = 'block';
            modelText.required = true;
            modelDropdown.required = false;
        } else {
            modelText.style.display = 'none';
            modelText.required = false;
            modelDropdown.required = true;
        }
    });
}

// Initialize dropdowns with "Other" options
function initializeDropdowns() {
    // Add "Other" option to storage dropdown
    const storageDropdown = document.getElementById('storage');
    const storageCustomInput = document.getElementById('storage_custom');
    const storageOtherOption = document.createElement('option');
    storageOtherOption.value = 'other';
    storageOtherOption.textContent = 'Other (Custom Storage)';
    storageDropdown.appendChild(storageOtherOption);

    storageDropdown.addEventListener('change', function() {
        if (this.value === 'other') {
            storageCustomInput.style.display = 'block';
            storageCustomInput.required = true;
        } else {
            storageCustomInput.style.display = 'none';
            storageCustomInput.required = false;
        }
    });

    // Add "Other" option to RAM dropdown
    const ramDropdown = document.getElementById('ram');
    const ramCustomInput = document.getElementById('ram_custom');
    const ramOtherOption = document.createElement('option');
    ramOtherOption.value = 'other';
    ramOtherOption.textContent = 'Other (Custom RAM)';
    ramDropdown.appendChild(ramOtherOption);

    ramDropdown.addEventListener('change', function() {
        if (this.value === 'other') {
            ramCustomInput.style.display = 'block';
            ramCustomInput.required = true;
        } else {
            ramCustomInput.style.display = 'none';
            ramCustomInput.required = false;
        }
    });
}

// Open custom device dialog
function openCustomDialog() {
    document.getElementById('custom_device_dialog').style.display = 'flex';
}

// Close custom device dialog
function closeCustomDialog() {
    document.getElementById('custom_device_dialog').style.display = 'none';
}

// Save custom device details and populate form fields
function saveCustomDialog() {
    const customBrand = document.getElementById('dialog_brand').value;
    const customModel = document.getElementById('dialog_model').value;
    const customStorage = document.getElementById('dialog_storage').value;
    const customRam = document.getElementById('dialog_ram').value;

    if (!customBrand || !customModel || !customStorage || !customRam) {
        showToast('Please fill in all custom device details');
        return;
    }

    // Set flag to prevent dialog from reopening
    isSettingBrandProgrammatically = true;

    // Add custom brand to dropdown if not exists
    const brandDropdown = document.getElementById('brand');
    let brandExists = false;
    for (let i = 0; i < brandDropdown.options.length; i++) {
        if (brandDropdown.options[i].value.toLowerCase() === customBrand.toLowerCase()) {
            brandExists = true;
            break;
        }
    }
    if (!brandExists) {
        const option = document.createElement('option');
        option.value = customBrand;
        option.textContent = customBrand;
        brandDropdown.appendChild(option);
    }
    
    // Select the custom brand
    brandDropdown.value = customBrand;

    // Reset flag after setting brand
    setTimeout(() => {
        isSettingBrandProgrammatically = false;
    }, 100);

    // For custom brand, use model text input instead of dropdown
    const modelText = document.getElementById('model_text');
    const modelDropdown = document.getElementById('model');
    modelText.style.display = 'block';
    modelDropdown.style.display = 'none';
    modelText.value = customModel;
    modelText.required = true;
    modelDropdown.required = false;

    // Set storage and RAM
    const storageDropdown = document.getElementById('storage');
    let storageExists = false;
    for (let i = 0; i < storageDropdown.options.length; i++) {
        if (storageDropdown.options[i].value === customStorage) {
            storageExists = true;
            break;
        }
    }
    if (!storageExists) {
        const option = document.createElement('option');
        option.value = customStorage;
        option.textContent = customStorage + ' GB';
        storageDropdown.appendChild(option);
    }
    storageDropdown.value = customStorage;

    const ramDropdown = document.getElementById('ram');
    let ramExists = false;
    for (let i = 0; i < ramDropdown.options.length; i++) {
        if (ramDropdown.options[i].value === customRam) {
            ramExists = true;
            break;
        }
    }
    if (!ramExists) {
        const option = document.createElement('option');
        option.value = customRam;
        option.textContent = customRam + ' GB';
        ramDropdown.appendChild(option);
    }
    ramDropdown.value = customRam;

    // Close dialog
    closeCustomDialog();
}


// Update questions based on device type
function updateQuestions() {
    const deviceType = document.getElementById('device_type').value;
    const questionsSection = document.getElementById('questions-section');
    
    questionsSection.innerHTML = '';
    
    if (!deviceType || !deviceQuestions[deviceType]) {
        return;
    }
    
    const questions = deviceQuestions[deviceType];
    
    questions.forEach(question => {
        const questionDiv = document.createElement('div');
        questionDiv.className = 'form-group';
        
        const label = document.createElement('label');
        label.textContent = question.text + ' *';
        questionDiv.appendChild(label);
        
        // Create a container for the buttons on the same line (Yes/No questions)
        const optionsContainer = document.createElement('div');
        optionsContainer.style.display = 'flex';
        optionsContainer.style.gap = '10px';
        optionsContainer.style.marginTop = '10px';
        
        // Sort options to ensure No is always first (left) and Yes is always second (right)
        const sortedOptions = [...question.options].sort((a, b) => {
            if (a.value === 'no' && b.value === 'yes') return -1;
            if (a.value === 'yes' && b.value === 'no') return 1;
            return 0;
        });
        
        sortedOptions.forEach(option => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'question-option-button';
            if (option.value === 'no') {
                button.classList.add('no-button');
            }
            button.textContent = option.label;
            button.dataset.questionId = question.id;
            button.dataset.value = option.value;
            
            button.addEventListener('click', function() {
                // Remove active class from all buttons for this question
                const allButtons = optionsContainer.querySelectorAll('.question-option-button');
                allButtons.forEach(btn => btn.classList.remove('active'));
                
                // Add active class to clicked button
                this.classList.add('active');
                
                // Create/update hidden radio input
                let radioInput = document.getElementById(question.id + '_radio');
                if (!radioInput) {
                    radioInput = document.createElement('input');
                    radioInput.type = 'radio';
                    radioInput.name = question.id;
                    radioInput.id = question.id + '_radio';
                    radioInput.style.display = 'none';
                    questionDiv.appendChild(radioInput);
                }
                radioInput.value = option.value;
                radioInput.checked = true;
            });
            
            optionsContainer.appendChild(button);
        });
        
        questionDiv.appendChild(optionsContainer);
        
        questionsSection.appendChild(questionDiv);
    });
}

// AI-like pricing database - base prices for known models
const modelPricingDatabase = {
    'Smartphone': {
        'iphone 15 pro max': 2800, 'iphone 15 pro': 2400, 'iphone 15 plus': 1800, 'iphone 15': 1600,
        'iphone 14 pro max': 2200, 'iphone 14 pro': 1900, 'iphone 14 plus': 1400, 'iphone 14': 1200,
        'iphone 13 pro max': 1600, 'iphone 13 pro': 1400, 'iphone 13': 1000, 'iphone 13 mini': 800,
        'iphone 12 pro max': 1200, 'iphone 12 pro': 1000, 'iphone 12': 800, 'iphone 12 mini': 600,
        'galaxy s24 ultra': 2600, 'galaxy s24 plus': 1800, 'galaxy s24': 1400,
        'galaxy s23 ultra': 2000, 'galaxy s23 plus': 1400, 'galaxy s23': 1100,
        'galaxy s22 ultra': 1400, 'galaxy s22 plus': 1000, 'galaxy s22': 800,
        'pixel 8 pro': 1200, 'pixel 8': 900, 'pixel 7 pro': 900, 'pixel 7': 700,
        'oneplus 12': 1300, 'oneplus 11': 1000, 'oneplus 10': 800
    },
    'Tablet': {
        'ipad pro 12.9': 1500, 'ipad pro 11': 1200, 'ipad air': 700, 'ipad': 400,
        'galaxy tab s9 ultra': 1200, 'galaxy tab s9 plus': 900, 'galaxy tab s9': 700
    },
    'Laptop': {
        'macbook pro 16': 2500, 'macbook pro 14': 2000, 'macbook air': 1200,
        'dell xps 15': 1800, 'dell xps 13': 1500,
        'hp spectre': 1400, 'hp envy': 1200,
        'lenovo thinkpad x1': 1600, 'lenovo yoga': 1000
    },
    'Smartwatch': {
        'apple watch ultra': 800, 'apple watch series 9': 500, 'apple watch se': 250,
        'galaxy watch 6': 400, 'galaxy watch 5': 300,
        'garmin fenix 7': 600, 'fitbit sense 2': 300
    }
};

// AI function to parse model name and extract pricing info
function parseModelForPricing(deviceType, brand, model) {
    const modelLower = model.toLowerCase();
    const brandLower = brand.toLowerCase();
    
    // Check if model exists in database
    const deviceModels = modelPricingDatabase[deviceType] || {};
    
    // Try exact match first
    for (const [dbModel, price] of Object.entries(deviceModels)) {
        if (modelLower === dbModel || modelLower.includes(dbModel)) {
            return price;
        }
    }
    
    // Try to extract year and calculate base price
    const yearMatch = modelLower.match(/\b(20\d{2}|1[3-5])\b/);
    const year = yearMatch ? parseInt(yearMatch[1]) : 2020;
    
    // Calculate base price based on year and device type
    let basePrice = 300;
    const currentYear = 2026;
    const age = currentYear - year;
    
    switch(deviceType) {
        case 'Smartphone':
            basePrice = Math.max(200, 1200 - (age * 200));
            if (modelLower.includes('pro') || modelLower.includes('ultra')) basePrice *= 1.5;
            if (modelLower.includes('max') || modelLower.includes('plus')) basePrice *= 1.3;
            if (modelLower.includes('mini') || modelLower.includes('se')) basePrice *= 0.7;
            break;
        case 'Tablet':
            basePrice = Math.max(200, 1000 - (age * 150));
            if (modelLower.includes('pro')) basePrice *= 1.5;
            break;
        case 'Laptop':
            basePrice = Math.max(300, 1800 - (age * 200));
            if (modelLower.includes('pro') || modelLower.includes('xps') || modelLower.includes('thinkpad')) basePrice *= 1.3;
            break;
        case 'Smartwatch':
            basePrice = Math.max(100, 500 - (age * 100));
            if (modelLower.includes('ultra') || modelLower.includes('fenix')) basePrice *= 1.5;
            break;
    }
    
    // Brand adjustments
    if (brandLower === 'apple' || brandLower === 'samsung') basePrice *= 1.2;
    if (brandLower === 'google' || brandLower === 'oneplus') basePrice *= 1.1;
    
    return basePrice;
}

// Calculate estimated price based on device specifications using AI-like algorithm
function calculateEstimate() {
    const deviceType = document.getElementById('device_type').value;
    const brand = document.getElementById('brand').value;
    const modelDropdown = document.getElementById('model');
    const modelText = document.getElementById('model_text');
    const storageDropdown = document.getElementById('storage');
    const storageCustom = document.getElementById('storage_custom');
    let storage = parseFloat(storageDropdown.value);
    if (storageDropdown.value === 'other') {
        storage = parseFloat(storageCustom.value);
    }
    const ramDropdown = document.getElementById('ram');
    const ramCustom = document.getElementById('ram_custom');
    let ram = parseFloat(ramDropdown.value);
    if (ramDropdown.value === 'other') {
        ram = parseFloat(ramCustom.value);
    }
    const batteryHealth = parseFloat(document.getElementById('battery_health').value);
    const accessories = document.getElementById('accessories').value;

    // Get model value (dropdown or text input)
    let modelValue = '';
    if (modelDropdown.value === 'other') {
        modelValue = modelText.value;
    } else if (modelText.style.display === 'block') {
        modelValue = modelText.value;
    } else {
        modelValue = modelDropdown.value;
    }

    if (!deviceType || !brand || !modelValue || !storage || !ram || !batteryHealth || !accessories) {
        showToast('Please fill in all required fields first');
        return;
    }

    // AI pricing: Parse model to get base price
    let estimate = parseModelForPricing(deviceType, brand, modelValue);

    // Get question answers and calculate condition score using weighted scoring (Yes/No format)
    let conditionScore = 1.0;
    const questions = deviceQuestions[deviceType] || [];
    
    questions.forEach(question => {
        const selected = document.querySelector(`input[name="${question.id}"]:checked`);
        if (selected) {
            const value = selected.value;
            // AI-weighted scoring based on question importance
            if (question.id === 'question_screen') {
                // Screen condition is most important (weight: 0.25)
                if (value === 'no') conditionScore *= 1.0;
                else if (value === 'yes') conditionScore *= 0.8;
            } else if (question.id === 'question_battery' || question.id === 'question_charging') {
                // Battery/charging is important (weight: 0.2)
                if (value === 'yes') conditionScore *= 1.0;
                else if (value === 'no') conditionScore *= 0.7;
            } else if (question.id === 'question_water') {
                // Water damage is critical (weight: 0.15)
                if (value === 'no') conditionScore *= 1.0;
                else if (value === 'yes') conditionScore *= 0.5;
            } else if (question.id === 'question_original') {
                // Original parts (weight: 0.1)
                if (value === 'yes') conditionScore *= 1.0;
                else if (value === 'no') conditionScore *= 0.9;
            } else {
                // Other factors (weight: 0.3 distributed)
                if (value === 'yes') conditionScore *= 1.0;
                else if (value === 'no') conditionScore *= 0.95;
            }
        }
    });

    estimate *= conditionScore;

    // Battery health adjustment (critical for mobile devices)
    if (deviceType === 'Smartphone' || deviceType === 'Tablet' || deviceType === 'Smartwatch') {
        estimate *= (batteryHealth / 100);
    }

    // Storage bonus (AI: higher storage adds more value for newer devices)
    const storageMultiplier = deviceType === 'Laptop' ? 0.05 : 0.08;
    if (storage >= 1024) estimate *= (1 + storageMultiplier * 4);
    else if (storage >= 512) estimate *= (1 + storageMultiplier * 3);
    else if (storage >= 256) estimate *= (1 + storageMultiplier * 2);
    else if (storage >= 128) estimate *= (1 + storageMultiplier);

    // RAM bonus (AI: more RAM significantly improves value)
    if (ram >= 32) estimate *= 1.2;
    else if (ram >= 16) estimate *= 1.15;
    else if (ram >= 12) estimate *= 1.1;
    else if (ram >= 8) estimate *= 1.05;

    // Accessories bonus (AI: original accessories add significant value)
    const accessoriesLower = accessories.toLowerCase();
    let accessoryBonus = 0;
    if (accessoriesLower.includes('box')) accessoryBonus += 50;
    if (accessoriesLower.includes('charger')) accessoryBonus += 30;
    if (accessoriesLower.includes('case')) accessoryBonus += 20;
    if (accessoriesLower.includes('headphone')) accessoryBonus += 25;
    if (accessoriesLower.includes('cable')) accessoryBonus += 15;
    
    estimate += accessoryBonus;

    // Ensure minimum price
    estimate = Math.max(50, estimate);

    // Update quotation summary
    document.getElementById('quote-estimate').textContent = 'RM' + estimate.toFixed(2);
}

// Validate form before submission
function validateForm() {
    const deviceType = document.getElementById('device_type').value;
    console.log('validateForm - device_type value:', deviceType);
    const brand = document.getElementById('brand').value;
    
    // For laptops, get model from text input, otherwise from dropdown
    let model;
    if (deviceType === 'Laptop') {
        model = document.getElementById('model_text').value;
    } else {
        model = document.getElementById('model').value;
    }
    
    if (!brand || !model) {
        showToast('Please select brand and model');
        return false;
    }

    // Validate condition
    const condition = document.getElementById('condition').value;
    if (!condition) {
        showToast('Please select device condition');
        return false;
    }

    // Check if all questions are answered
    const questions = deviceQuestions[deviceType] || [];
    
    for (const question of questions) {
        const answered = document.querySelector(`input[name="${question.id}"]:checked`);
        if (!answered) {
            showToast('Please answer all questions about your device condition');
            return false;
        }
    }

    // Validate images (2-3 required)
    if (sellImageFiles.length < 2) {
        showToast('Please upload at least 2 images');
        return false;
    }

    // Validate video
    if (!sellVideoFile) {
        showToast('Please upload a video');
        return false;
    }

    if (sellVideoFile.size > MAX_VIDEO_SIZE_BYTES) {
        showToast('Video too large. Please upload a video smaller than 40MB.');
        return false;
    }

    for (const file of sellImageFiles) {
        if (file.size > MAX_IMAGE_SIZE_BYTES) {
            showToast('One or more images are too large. Please use images smaller than 10MB each.');
            return false;
        }
    }

    // Sync files to file inputs before submission
    const imageInput = document.getElementById('sellImages');
    if (imageInput && sellImageFiles.length > 0) {
        const dt = new DataTransfer();
        sellImageFiles.forEach(f => dt.items.add(f));
        imageInput.files = dt.files;
    }

    const videoInput = document.getElementById('sellVideo');
    if (videoInput && sellVideoFile) {
        const dt = new DataTransfer();
        dt.items.add(sellVideoFile);
        videoInput.files = dt.files;
    }

    return true;
}

// Image upload handling for sell page
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('sellImages');
    const imageList = document.getElementById('sellImageList');
    const videoInput = document.getElementById('sellVideo');
    const videoPreview = document.getElementById('sellVideoPreview');

    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            
            // Add new files to the array and validate file sizes
            files.forEach(file => {
                if (sellImageFiles.length < 3) {
                    if (file.size > MAX_IMAGE_SIZE_BYTES) {
                        showToast('Image "' + file.name + '" is too large. Please use images smaller than 10MB.');
                    } else {
                        sellImageFiles.push(file);
                    }
                }
            });

            // Update the file input
            const dt = new DataTransfer();
            sellImageFiles.forEach(f => dt.items.add(f));
            imageInput.files = dt.files;

            // Update the display
            updateSellImageList();
        });
    }

    if (videoInput) {
        videoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > MAX_VIDEO_SIZE_BYTES) {
                    showToast('Video is too large. Please upload a video smaller than 40MB.');
                    videoInput.value = '';
                    sellVideoFile = null;
                    updateSellVideoPreview();
                } else {
                    sellVideoFile = file;
                    updateSellVideoPreview();
                }
            }
        });
    }
});

function updateSellImageList() {
    const imageList = document.getElementById('sellImageList');
    if (!imageList) return;

    imageList.innerHTML = '';

    sellImageFiles.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'image-item';
        const imageUrl = URL.createObjectURL(file);
        item.innerHTML = `
            <button type="button" class="remove-image" onclick="removeSellImage(${index})">×</button>
            <img src="${imageUrl}" alt="${file.name}" class="image-preview">
            <span class="image-name">${file.name}</span>
        `;
        imageList.appendChild(item);
    });
}

function removeSellImage(index) {
    const imageInput = document.getElementById('sellImages');
    
    // Remove the file from the array
    sellImageFiles.splice(index, 1);

    // Update the file input with remaining files
    const dt = new DataTransfer();
    sellImageFiles.forEach(f => dt.items.add(f));
    imageInput.files = dt.files;

    // Update the display
    updateSellImageList();
}

function updateSellVideoPreview() {
    const videoPreview = document.getElementById('sellVideoPreview');
    if (!videoPreview) return;

    videoPreview.innerHTML = '';

    if (sellVideoFile) {
        const videoUrl = URL.createObjectURL(sellVideoFile);
        videoPreview.innerHTML = `
            <video src="${videoUrl}" class="video-preview" controls></video>
            <button type="button" class="remove-video" onclick="removeSellVideo()">Remove Video</button>
        `;
    }
}

function removeSellVideo() {
    const videoInput = document.getElementById('sellVideo');
    sellVideoFile = null;
    videoInput.value = '';
    updateSellVideoPreview();
}
