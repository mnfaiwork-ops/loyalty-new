{{-- Kolom PIN: kotak tampilan, tombol Lihat, dan tombol kirim yang aktif setelah semua kolom wajib terisi (desain 4a-4e). --}}
<script>
    (() => {
        const LENGTH = {{ \LoyaltyNew\Core\Customers\CustomerPin::LENGTH }};
        const form = document.querySelector('[data-pin-form]');
        if (!form) return;
        const submit = form.querySelector('[type="submit"]');
        const phone = form.querySelector('#phone');

        const fields = [...form.querySelectorAll('[data-pin-field]')].map(root => {
            const input = root.querySelector('[data-pin]');
            const boxes = [...root.querySelectorAll('.pin-box')];
            const toggle = root.querySelector('[data-pin-toggle]');
            const alert = root.querySelector('[data-pin-error]');
            const field = { root, input, shown: false };

            field.paint = () => {
                const focused = document.activeElement === input;
                const active = Math.min(input.value.length, LENGTH - 1);
                boxes.forEach((box, i) => {
                    const digit = input.value[i] ?? '';
                    box.textContent = field.shown ? digit : '';
                    box.classList.toggle('is-dot', !field.shown && digit !== '');
                    box.classList.toggle('is-active', focused && i === active);
                });
            };
            field.show = shown => {
                field.shown = shown;
                input.type = shown ? 'text' : 'password';
                toggle.textContent = shown ? 'Sembunyikan' : 'Lihat';
                toggle.setAttribute('aria-pressed', String(shown));
                field.paint();
            };
            field.setError = message => {
                root.classList.toggle('is-error', Boolean(message));
                input.classList.toggle('is-error', Boolean(message));
                alert.hidden = !message;
                alert.querySelector('span').textContent = message || '';
                if (message) input.setAttribute('aria-invalid', 'true');
                else input.removeAttribute('aria-invalid');
            };

            root.classList.add('is-enhanced');
            toggle.hidden = false;
            toggle.addEventListener('click', () => { field.show(!field.shown); input.focus(); });
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g, '').slice(0, LENGTH);
                // Galat tidak menegur angka yang sedang diketik ulang.
                if (root.classList.contains('is-error')) field.setError(null);
                field.paint();
                sync();
            });
            ['focus', 'blur'].forEach(type => input.addEventListener(type, field.paint));
            // Kursor selalu di akhir, karena kotak hanya bisa diisi berurutan.
            input.addEventListener('select', () => input.setSelectionRange(input.value.length, input.value.length));
            return field;
        });

        const sync = () => {
            const pinsOk = fields.every(f => f.input.value.length === LENGTH);
            const phoneOk = !phone || phone.value.replace(/\D/g, '').length >= 8;
            submit.disabled = !(pinsOk && phoneOk);
        };
        phone?.addEventListener('input', sync);

        // Aturan PIN baru sama dengan CustomerPin::problem(), dicek saat tombol ditekan, bukan saat mengetik.
        const guessable = pin => new Set(pin).size === 1 || '0123456789'.includes(pin) || '9876543210'.includes(pin);
        form.addEventListener('submit', event => {
            if (form.hasAttribute('data-pin-rules')) {
                const [pin, again] = fields;
                if (guessable(pin.input.value)) {
                    event.preventDefault();
                    pin.setError('PIN ini terlalu mudah ditebak. Hindari angka yang sama semua atau berurutan.');
                    pin.show(true);
                    pin.input.focus();
                    return;
                }
                if (pin.input.value !== again.input.value) {
                    event.preventDefault();
                    again.setError('PIN kedua tidak sama dengan yang pertama.');
                    again.input.value = '';
                    again.input.focus();
                    sync();
                    return;
                }
            }
            submit.classList.add('is-loading');
            submit.textContent = 'Mengecek…';
        });

        fields.forEach(f => f.paint());
        sync();
        // Galat dari server: fokus ke kolom yang ditandai, bukan kolom pertama.
        const errored = fields.find(f => f.root.classList.contains('is-error'));
        if (errored) errored.input.focus();
    })();
</script>
