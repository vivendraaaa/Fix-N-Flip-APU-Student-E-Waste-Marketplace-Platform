function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Accept repair request
function acceptRepair(requestId) {
    if (confirm('Are you sure you want to accept this repair request?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'technician_repairs.php';
        form.innerHTML = '<input type="hidden" name="action" value="accept"><input type="hidden" name="request_id" value="' + requestId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Reject repair request
function rejectRepair(requestId) {
    const reason = prompt('Please enter the reason for rejection:');
    if (reason) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'technician_repairs.php';
        form.innerHTML = '<input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="' + requestId + '"><input type="hidden" name="rejection_reason" value="' + encodeURIComponent(reason) + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Confirm device pickup
function confirmPickup(requestId) {
    const code = prompt('Enter the pickup code to confirm:');
    if (code) {
        fetch('technician_repairs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=confirm_pickup&request_id=' + requestId + '&pickup_code=' + encodeURIComponent(code)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pickup confirmed successfully!');
                location.reload();
            } else {
                alert(data.error || 'Invalid pickup code');
            }
        });
    }
}

// Complete repair
function completeRepair(requestId) {
    // Works from Dashboard and Repairs page. The listing form will mark the repair as completed after submission.
    window.location.href = 'technician.php?section=create_listing&repair_id=' + encodeURIComponent(requestId);
}

// Declare device as unrepairable
function declareUnrepairable(requestId) {
    showTechnicianDialog({
        title: 'Mark Device as Unrepairable',
        label: 'Reason why this device cannot be repaired',
        textareaId: 'unrepairableReasonInput',
        submitText: 'Submit',
        danger: true,
        onSubmit: function(reason) {
            if (!reason.trim()) {
                alert('Please enter the reason.');
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'technician_repairs.php';
            form.innerHTML = '<input type="hidden" name="action" value="declare_unrepairable">' +
                '<input type="hidden" name="request_id" value="' + requestId + '">' +
                '<input type="hidden" name="unrepairable_reason" value="' + escapeHtmlAttr(reason) + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

// Accept disassembly request
function acceptDisassembly(requestId) {
    if (confirm('Are you sure you want to accept this disassembly request?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'technician_disassembly.php';
        form.innerHTML = '<input type="hidden" name="action" value="accept"><input type="hidden" name="request_id" value="' + requestId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Reject disassembly request
function rejectDisassembly(requestId) {
    const reason = prompt('Please enter the reason for rejection:');
    if (reason) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'technician_disassembly.php';
        form.innerHTML = '<input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="' + requestId + '"><input type="hidden" name="rejection_reason" value="' + encodeURIComponent(reason) + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Toggle password visibility
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const svg = button.querySelector('svg');

    if (input.type === 'password') {
        input.type = 'text';
        svg.innerHTML = `
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
        `;
    } else {
        input.type = 'password';
        svg.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        `;
    }
}

// View repair request details
function viewRepairRequest(requestId) {
   fetch('get_repair_request_details.php?id=' + requestId, { credentials: 'same-origin' })
        .then(async response => {
            const text = await response.text();
            const contentType = response.headers.get('content-type') || '';

            if (!response.ok) {
                throw new Error('Server error ' + response.status + ': ' + text.trim().slice(0, 200));
            }
            if (!contentType.includes('application/json')) {
                throw new Error('Invalid JSON response: ' + text.trim().slice(0, 200));
            }

            try {
                return JSON.parse(text);
            } catch (error) {
                throw new Error('JSON parse error: ' + error.message + ' - ' + text.trim().slice(0, 200));
            }
        })
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                console.log('Full error response:', data);
            } else {
                let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0;">';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Device Type:</strong> ' + data.device_type + '</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Brand:</strong> ' + data.brand + '</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Model:</strong> ' + data.model + '</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Condition:</strong> ' + data.device_condition + '</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Storage:</strong> ' + (data.storage || 'N/A') + 'GB</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>RAM:</strong> ' + (data.ram || 'N/A') + 'GB</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Battery Health:</strong> ' + (data.battery_health || 'N/A') + '%</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Screen Condition:</strong> ' + (data.screen_condition || 'N/A') + '</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Est. Price:</strong> RM' + (data.estimated_price || '0') + '</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981;"><strong>Seller:</strong> ' + data.seller_name + '</div>';
                html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981; grid-column: 1 / -1;"><strong>Submitted:</strong> ' + data.submitted_at + '</div>';
                html += '</div>';
                
                if (data.issues) {
                    html += '<div style="padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107; margin: 15px 0;"><strong>Issues:</strong> ' + data.issues + '</div>';
                }
                
                if (data.accessories) {
                    html += '<div style="padding: 15px; background: #d1fae5; border-radius: 8px; border-left: 4px solid #10b981; margin: 15px 0;"><strong>Accessories:</strong> ' + data.accessories + '</div>';
                }
                
                if (data.description) {
                    html += '<div style="padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981; margin: 15px 0;"><strong>Description:</strong> ' + data.description + '</div>';
                }
                
                if (data.repair_status === 'repairing' && data.parts_requested) {
                    const decodedParts = decodeURIComponent(data.parts_requested);
                    const pinText = data.parts_request_pin ? ' (PIN: ' + data.parts_request_pin + ')' : '';
                    html += '<div style="padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107; margin: 15px 0;"><strong>Parts Requested' + pinText + ':</strong><br>' + decodedParts.replace(/\n/g, '<br>') + '</div>';
                }
                
                // Show different buttons based on status
                html += '<div style="margin-top: 25px; display: flex; gap: 10px; flex-wrap: wrap;">';

                if (data.repair_status === 'pending') {
                    html += '<button type="button" class="btn btn-success" onclick="acceptRepair(' + requestId + ')">Accept</button>';
                    html += '<button type="button" class="btn btn-danger" onclick="rejectRepair(' + requestId + ')">Reject</button>';
                } else if (data.repair_status === 'accepted') {
                    html += '<button type="button" class="btn btn-primary" onclick="startRepair(' + requestId + ')">Start Repair</button>';
                    html += '<button type="button" class="btn btn-danger" onclick="disassembleDevice(' + requestId + ')">Disassemble</button>';
                } else if (data.repair_status === 'repairing') {
                    html += '<button type="button" class="btn btn-primary" onclick="endRepair(' + requestId + ')">End Repair</button>';
                    html += '<button type="button" class="btn btn-danger" onclick="disassembleDevice(' + requestId + ')">Disassemble</button>';
                    html += '<button type="button" class="btn btn-warning" onclick="showPartsRequest(' + requestId + ')">Request for Parts</button>';
                } else if (data.repair_status === 'completed') {
                    html += '<button type="button" class="btn btn-success" onclick="listForSale(' + requestId + ')">List for Sale</button>';
                }

                html += '</div>';
                
                // Parts request form (hidden by default)
                html += '<div id="partsRequestForm" style="display: none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">';
                html += '<label style="display: block; margin-bottom: 10px; font-weight: bold;">Parts Needed:</label>';
                html += '<textarea id="partsNeeded" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" placeholder="List the parts you need..."></textarea>';
                html += '<p style="margin-top: 10px; color: #555;">A 4-digit request PIN will be generated automatically.</p>';
                html += '<div style="margin-top: 10px; display: flex; gap: 10px;">';
                html += '<button type="button" class="btn btn-success" onclick="submitPartsRequest(' + requestId + ')">Submit Request</button>';
                html += '<button type="button" class="btn btn-secondary" onclick="hidePartsRequest()">Cancel</button>';
                html += '</div>';
                html += '</div>';
                
                document.getElementById('repairRequestDetails').innerHTML = html;
                openModal('viewRepairModal');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading repair request details: ' + error.message);
        });
}

// Start repair
function startRepair(requestId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'technician_repairs.php';
    form.innerHTML = '<input type="hidden" name="action" value="start_repair"><input type="hidden" name="request_id" value="' + requestId + '">';
    document.body.appendChild(form);
    form.submit();
}

// End repair
function endRepair(requestId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'technician_repairs.php';
    form.innerHTML = '<input type="hidden" name="action" value="end_repair"><input type="hidden" name="request_id" value="' + requestId + '">';
    document.body.appendChild(form);
    form.submit();
}

// List for sale
function listForSale(requestId) {
    window.location.href = 'technician.php?section=create_listing&repair_id=' + requestId;
}

// Disassemble device
function disassembleDevice(requestId) {
    showTechnicianDialog({
        title: 'Request Clerk Permission for Disassembly',
        label: 'Reason for disassembly request',
        textareaId: 'disassemblyReasonInput',
        submitText: 'Send Request',
        danger: true,
        placeholder: 'Example: Device cannot be repaired and should be dismantled for reusable parts.',
        onSubmit: function(reason) {
            if (!reason.trim()) {
                alert('Please enter a reason before requesting disassembly.');
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'technician_repairs.php';
            form.innerHTML = '<input type="hidden" name="action" value="disassemble">' +
                '<input type="hidden" name="request_id" value="' + requestId + '">' +
                '<input type="hidden" name="disassembly_reason" value="' + escapeHtmlAttr(reason) + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function escapeHtmlAttr(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function showTechnicianDialog(options) {
    let modal = document.getElementById('technicianActionModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'technicianActionModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="technicianActionTitle"></h3>
                    <button class="close-modal" type="button" onclick="closeModal('technicianActionModal')">&times;</button>
                </div>
                <div class="form-group">
                    <label id="technicianActionLabel" for="technicianActionText"></label>
                    <textarea id="technicianActionText" rows="5" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:6px;"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('technicianActionModal')">Cancel</button>
                    <button type="button" class="btn" id="technicianActionSubmit"></button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }

    document.getElementById('technicianActionTitle').textContent = options.title || 'Action';
    document.getElementById('technicianActionLabel').textContent = options.label || 'Details';
    const textarea = document.getElementById('technicianActionText');
    textarea.value = '';
    textarea.placeholder = options.placeholder || '';
    const submit = document.getElementById('technicianActionSubmit');
    submit.textContent = options.submitText || 'Submit';
    submit.className = options.danger ? 'btn btn-danger' : 'btn btn-primary';
    submit.onclick = function() { options.onSubmit(textarea.value); };
    openModal('technicianActionModal');
}

// Show parts request form
function showPartsRequest(requestId) {
    document.getElementById('partsRequestForm').style.display = 'block';
}

// Hide parts request form
function hidePartsRequest() {
    document.getElementById('partsRequestForm').style.display = 'none';
}

// Submit parts request
function submitPartsRequest(requestId) {
    const parts = document.getElementById('partsNeeded').value;
    
    if (!parts.trim()) {
        alert('Please enter the parts you need.');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'technician_repairs.php';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'request_parts';
    form.appendChild(actionInput);

    const requestInput = document.createElement('input');
    requestInput.type = 'hidden';
    requestInput.name = 'request_id';
    requestInput.value = requestId;
    form.appendChild(requestInput);

    const partsInput = document.createElement('input');
    partsInput.type = 'hidden';
    partsInput.name = 'parts_needed';
    partsInput.value = parts;
    form.appendChild(partsInput);

    document.body.appendChild(form);
    form.submit();
}
