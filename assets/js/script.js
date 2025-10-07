/*!
* Custom JS for the Admin Panel
*/

window.addEventListener('DOMContentLoaded', event => {

    // 1. Sidebar Toggle
    // ==================================================
    const sidebarToggle = document.body.querySelector('#sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', event => {
            event.preventDefault();
            document.body.classList.toggle('sb-sidenav-toggled');
            // Jika Anda ingin menyimpan status sidebar di localStorage, tambahkan logikanya di sini.
        });
    }


    // 2. User Management Modal Logic
    // ==================================================
    const userModal = document.getElementById('userModal');
    if (userModal) {
        userModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget; // Tombol yang memicu modal
            const action = button.getAttribute('data-action');

            const modalTitle = userModal.querySelector('.modal-title');
            const form = document.getElementById('userForm');
            const passwordHelp = document.getElementById('passwordHelp');

            // Reset form
            form.reset();

            if (action === 'edit') {
                modalTitle.textContent = 'Edit User';
                form.querySelector('#formAction').value = 'edit';

                // Isi data dari tombol ke form
                form.querySelector('#userId').value = button.getAttribute('data-id');
                form.querySelector('#username').value = button.getAttribute('data-username');
                form.querySelector('#email').value = button.getAttribute('data-email');
                form.querySelector('#role').value = button.getAttribute('data-role');
                form.querySelector('#password').removeAttribute('required');
                passwordHelp.classList.remove('d-none');

            } else { // 'add' action
                modalTitle.textContent = 'Tambah User Baru';
                form.querySelector('#formAction').value = 'add';
                form.querySelector('#userId').value = '';
                form.querySelector('#password').setAttribute('required', 'required');
                passwordHelp.classList.add('d-none');
            }
        });
    }


    // 3. AJAX Video Upload with Progress Bar
    // ==================================================
    const uploadForm = document.getElementById('uploadForm');
    // 4. Bootstrap Tooltip Initialization
    // ==================================================
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });


    if (uploadForm) {
        // Channel Selection Logic
        const selectAllBtn = document.getElementById('selectAllChannels');
        const deselectAllBtn = document.getElementById('deselectAllChannels');
        const channelList = document.getElementById('channelList');

        if (selectAllBtn && deselectAllBtn && channelList) {
            const checkboxes = channelList.querySelectorAll('input[type="checkbox"]');

            selectAllBtn.addEventListener('click', () => {
                checkboxes.forEach(cb => cb.checked = true);
            });

            deselectAllBtn.addEventListener('click', () => {
                checkboxes.forEach(cb => cb.checked = false);
            });
        }

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate at least one channel is selected
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

            // Validasi file
            const videoFile = document.getElementById('video_file').files[0];
            if (!videoFile) {
                uploadStatus.innerHTML = '<div class="alert alert-danger">Pilih file video terlebih dahulu.</div>';
                return;
            }

            // Tampilkan progress bar dan nonaktifkan tombol
            progressBarContainer.classList.remove('d-none');
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            uploadStatus.innerHTML = '';
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Mengupload...';

            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(event) {
                if (event.lengthComputable) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = percentComplete + '%';
                }
            });

            xhr.addEventListener('load', function() {
                // Aktifkan kembali tombol
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i> Upload Video';

                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.status === 'success') {
                            uploadStatus.innerHTML = `<div class="alert alert-success"><strong>Berhasil!</strong> ${response.message} ID Video: ${response.data.videoId}</div>`;
                            uploadForm.reset();
                            progressBar.classList.add('bg-success');
                        } else {
                            uploadStatus.innerHTML = `<div class="alert alert-danger"><strong>Error:</strong> ${response.message}</div>`;
                            progressBar.classList.add('bg-danger');
                        }
                    } catch (e) {
                        uploadStatus.innerHTML = `<div class="alert alert-danger"><strong>Error:</strong> Gagal mem-parsing respons dari server.</div>`;
                        progressBar.classList.add('bg-danger');
                    }
                } else {
                    uploadStatus.innerHTML = `<div class="alert alert-danger"><strong>Error:</strong> Terjadi kesalahan pada server (Status: ${xhr.status}).</div>`;
                    progressBar.classList.add('bg-danger');
                }
            });

            xhr.addEventListener('error', function() {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i> Upload Video';
                uploadStatus.innerHTML = '<div class="alert alert-danger"><strong>Error:</strong> Terjadi kesalahan jaringan saat mengupload.</div>';
                progressBar.classList.add('bg-danger');
            });

            xhr.open('POST', 'handle_upload.php', true);
            xhr.send(formData);
        });
    }
});