/*!
* Custom JS for the Admin Panel (Versi 2 - Diperbaiki)
*/

window.addEventListener('DOMContentLoaded', event => {

    // 1. Sidebar Toggle
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
        });
    }

    // 2. User Management Modal Logic
    const userModal = document.getElementById('userModal');
    if (userModal) {
        userModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const action = button.getAttribute('data-action');
            const modalTitle = userModal.querySelector('.modal-title');
            const form = document.getElementById('userForm');
            const passwordHelp = document.getElementById('passwordHelp');
            form.reset();
            if (action === 'edit') {
                modalTitle.textContent = 'Edit User';
                form.querySelector('#formAction').value = 'edit';
                form.querySelector('#userId').value = button.getAttribute('data-id');
                form.querySelector('#username').value = button.getAttribute('data-username');
                form.querySelector('#email').value = button.getAttribute('data-email');
                form.querySelector('#role').value = button.getAttribute('data-role');
                form.querySelector('#password').removeAttribute('required');
                passwordHelp.classList.remove('d-none');
            } else {
                modalTitle.textContent = 'Tambah User Baru';
                form.querySelector('#formAction').value = 'add';
                form.querySelector('#userId').value = '';
                form.querySelector('#password').setAttribute('required', 'required');
                passwordHelp.classList.add('d-none');
            }
        });
    }

    // 3. Bootstrap Tooltip Initialization
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // 4. AJAX Video Upload with Progress Bar & Detailed Reporting
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        const selectAllBtn = document.getElementById('selectAllChannels');
        const deselectAllBtn = document.getElementById('deselectAllChannels');
        const channelList = document.getElementById('channelList');

        if (selectAllBtn && deselectAllBtn && channelList) {
            const checkboxes = channelList.querySelectorAll('input[type="checkbox"]');
            selectAllBtn.addEventListener('click', () => checkboxes.forEach(cb => cb.checked = true));
            deselectAllBtn.addEventListener('click', () => checkboxes.forEach(cb => cb.checked = false));
        }

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const checkedChannels = channelList.querySelectorAll('input[type="checkbox"]:checked');
            if (checkedChannels.length === 0) {
                alert('Silakan pilih setidaknya satu channel tujuan.');
                return;
            }

            const formData = new FormData(this);
            const progressBarContainer = document.getElementById('progressBarContainer');
            const progressBar = document.getElementById('progressBar');
            const uploadStatus = document.getElementById('uploadStatus');
            const submitButton = document.getElementById('submitButton');

            // Reset UI
            progressBarContainer.classList.remove('d-none');
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            progressBar.classList.remove('bg-success', 'bg-danger');
            uploadStatus.innerHTML = '';
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Mengupload...';

            const xhr = new XMLHttpRequest();

            // Progress bar
            xhr.upload.addEventListener('progress', function(event) {
                if (event.lengthComputable) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = percentComplete + '%';
                }
            });

            // Handle response
            xhr.addEventListener('load', function() {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fa-solid fa-cloud-arrow-up me-2"></i>Mulai Upload';
                progressBar.style.width = '100%';

                if (xhr.status >= 200 && xhr.status < 400) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        let reportHTML = `<h5 class="mt-4">Laporan Upload</h5>`;

                        if (response.status === 'completed') {
                            progressBar.classList.add('bg-success');
                            progressBar.textContent = 'Selesai';

                            if (response.data.success.length > 0) {
                                reportHTML += `<div class="alert alert-success"><h6>Berhasil:</h6><ul class="mb-0">`;
                                response.data.success.forEach(msg => {
                                    reportHTML += `<li>${msg}</li>`;
                                });
                                reportHTML += `</ul></div>`;
                            }
                            if (response.data.failed.length > 0) {
                                reportHTML += `<div class="alert alert-danger"><h6>Gagal:</h6><ul class="mb-0">`;
                                response.data.failed.forEach(msg => {
                                    reportHTML += `<li>${msg}</li>`;
                                });
                                reportHTML += `</ul></div>`;
                            }
                        } else { // status === 'error'
                            progressBar.classList.add('bg-danger');
                            progressBar.textContent = 'Gagal';
                            reportHTML = `<div class="alert alert-danger"><strong>Error:</strong> ${response.message}</div>`;
                        }
                        uploadStatus.innerHTML = reportHTML;

                    } catch (err) {
                        progressBar.classList.add('bg-danger');
                        uploadStatus.innerHTML = `<div class="alert alert-danger"><strong>Error:</strong> Gagal memproses respons dari server.</div>`;
                    }
                } else {
                    progressBar.classList.add('bg-danger');
                    uploadStatus.innerHTML = `<div class="alert alert-danger"><strong>Error:</strong> Terjadi kesalahan pada server (Status: ${xhr.status}).</div>`;
                }
            });

            // Handle network error
            xhr.addEventListener('error', function() {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="fa-solid fa-cloud-arrow-up me-2"></i>Mulai Upload';
                progressBar.classList.add('bg-danger');
                uploadStatus.innerHTML = '<div class="alert alert-danger"><strong>Error:</strong> Terjadi kesalahan jaringan saat mengupload.</div>';
            });

            xhr.open('POST', 'handle_upload.php', true);
            xhr.send(formData);
        });
    }
});