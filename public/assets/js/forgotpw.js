document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('forgot-form');
    const success = document.getElementById('success-notification');
    const subtitle = document.getElementById('forgot-subtitle');
    const group = document.getElementById('email-group');
    const btn = document.getElementById('submit-forgot');
    const error = document.getElementById('forgot-error');
    const API_BASE = (window.location.protocol === 'file:' || (window.location.port && window.location.port !== '8000')) ? 'http://127.0.0.1:8000' : '';
    if (!form) return;
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const email = document.getElementById('recovery-email').value.trim().toLowerCase();
        error.textContent = '';
        if (!email) { error.textContent = 'Email wajib diisi.'; return; }
        const originalLabel = btn.textContent;
        try {
            btn.disabled = true;
            btn.textContent = 'Memproses...';
            const response = await fetch(`${API_BASE}/api/auth/forgot-password`, {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({email})});
            const data = await response.json();
            if (!response.ok) { error.textContent = data.message || 'Permintaan reset password gagal.'; return; }
            success.querySelector('p').textContent = data.message || 'Permintaan reset telah diproses. Jika alamat tersebut terdaftar dan email dapat dikirim, tautan reset akan masuk ke inbox.';
            group.classList.add('hidden'); btn.classList.add('hidden'); subtitle.style.display='none'; form.querySelector('.register-link')?.classList.add('hidden'); success.classList.remove('hidden');
        } catch (err) { console.error(err); error.textContent='Tidak dapat terhubung ke server. Silakan coba lagi.'; } finally { btn.disabled=false; btn.textContent=originalLabel; }
    });
});
