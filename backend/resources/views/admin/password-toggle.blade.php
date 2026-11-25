{{-- Tombol mata di setiap kolom kata sandi: mata biasa = tersembunyi, mata dicoret = sedang terlihat. --}}
<script>
    (function () {
        const eye = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
        const eyeOff = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 19c-7 0-11-7-11-7a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';
        document.querySelectorAll('input[type="password"]').forEach(function (input) {
            const wrap = document.createElement('span');
            wrap.className = 'pw-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pw-toggle';
            btn.innerHTML = eye;
            btn.setAttribute('aria-label', 'Tampilkan kata sandi');
            btn.addEventListener('click', function () {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = show ? eyeOff : eye;
                btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
                input.focus();
            });
            wrap.appendChild(btn);
        });
    })();
</script>
