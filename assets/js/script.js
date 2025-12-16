error_reporting(E_ALL);
ini_set('display_errors', 1);
// assets/js/script.js
// Fungsi untuk menampilkan toast
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    
    if (!toastContainer) {
        // Buat container toast jika belum ada
        const container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '1050';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast show align-items-center text-white bg-${type} border-0`;
    toast.role = 'alert';
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.style.marginBottom = '10px';
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    document.getElementById('toastContainer').appendChild(toast);
    
    // Hapus toast setelah 3 detik
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Event listener untuk demo login
document.addEventListener('DOMContentLoaded', function() {
    // Demo login buttons
    const demoButtons = document.querySelectorAll('.demo-btn');
    demoButtons.forEach(button => {
        button.addEventListener('click', function() {
            const user = this.getAttribute('data-user');
            let username = '', password = '';
            
            switch(user) {
                case 'admin':
                    username = 'admin';
                    password = 'admin123';
                    break;
                case 'walikelas':
                    username = 'wali_kelas';
                    password = 'wali123';
                    break;
                case 'walimurid':
                    username = 'wali_murid';
                    password = 'murid123';
                    break;
            }
            
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
        });
    });
});

// Preview foto sebelum upload
document.getElementById('fotoInput').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        var reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('fotoPreview').src = e.target.result;
        }
        
        reader.readAsDataURL(this.files[0]);
    }
});

// Hapus kode yang tidak perlu dan tambahkan ini:
document.addEventListener('DOMContentLoaded', function() {
    const spans = document.querySelectorAll('#loginMessage span');
    
    spans.forEach(span => {
        // Trigger animasi dengan memaksa reflow
        void span.offsetWidth;
        span.style.animationPlayState = 'running';
    });
});