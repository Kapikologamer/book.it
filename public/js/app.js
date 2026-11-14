'use strict';

const yearElement = document.querySelector('#current-year');

if (yearElement) {
    yearElement.textContent = String(new Date().getFullYear());
}

document.querySelectorAll('[data-pin]').forEach((wrapper) => {
    const original = wrapper.querySelector('input');
    const group = document.createElement('div');
    group.className = 'pin-segments';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-labelledby', `${original.id}-label`);
    const digits = Array.from({ length: 6 }, (_, index) => {
        const digit = document.createElement('input');
        digit.type = 'password';
        digit.inputMode = 'numeric';
        digit.maxLength = 1;
        digit.pattern = '[0-9]';
        digit.required = true;
        digit.autocomplete = 'off';
        digit.id = `${original.id}-digit-${index + 1}`;
        digit.setAttribute('aria-label', `Cyfra ${index + 1} z 6`);
        digit.setAttribute('aria-describedby', original.getAttribute('aria-describedby'));
        if (original.hasAttribute('aria-invalid')) digit.setAttribute('aria-invalid', 'true');
        group.append(digit);
        return digit;
    });
    const sync = () => { original.value = digits.map((digit) => digit.value).join(''); };
    digits.forEach((digit, index) => {
        digit.addEventListener('input', () => {
            digit.value = digit.value.replace(/[^0-9]/g, '').slice(-1);
            sync();
            if (digit.value && index < 5) digits[index + 1].focus();
        });
        digit.addEventListener('focus', () => digit.select());
        digit.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft' && index > 0) { event.preventDefault(); digits[index - 1].focus(); }
            if (event.key === 'ArrowRight' && index < 5) { event.preventDefault(); digits[index + 1].focus(); }
            if (event.key === 'Backspace' && !digit.value && index > 0) {
                event.preventDefault(); digits[index - 1].value = ''; digits[index - 1].focus(); sync();
            }
        });
        digit.addEventListener('paste', (event) => {
            event.preventDefault();
            const value = event.clipboardData.getData('text');
            if (!/^[0-9]{1,6}$/.test(value)) return;
            const start = value.length === 6 ? 0 : index;
            [...value].slice(0, 6 - start).forEach((char, offset) => { digits[start + offset].value = char; });
            sync();
            digits[Math.min(5, start + value.length)].focus();
        });
    });
    original.type = 'hidden';
    original.required = false;
    group.id = original.id;
    original.id += '-value';
    original.after(group);
    wrapper.querySelector('label').htmlFor = digits[0].id;
});

const errorSummary = document.querySelector('.error-summary');
if (errorSummary) errorSummary.focus();

document.querySelectorAll('[data-mg-preview]').forEach((output) => {
    const input = document.querySelector(`#mg-${CSS.escape(output.dataset.mgPreview)}`);
    if (!input) return;
    const update = () => { output.textContent = input.value.trim(); };
    input.addEventListener('input', update);
});

['logo', 'banner'].forEach((name) => {
    const input = document.querySelector(`#mg-${name}`);
    const image = document.querySelector(`[data-mg-image="${name}"]`);
    const remove = document.querySelector(`[name="remove_${name}"]`);
    const initials = name === 'logo' ? document.querySelector('[data-mg-initials]') : null;
    if (!input || !image) return;
    let objectUrl = null;
    const updateFallback = (hidden) => {
        if (initials) initials.hidden = !hidden;
    };
    input.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = input.files && input.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        objectUrl = URL.createObjectURL(file);
        image.src = objectUrl;
        image.hidden = false;
        updateFallback(false);
        if (remove) remove.checked = false;
    });
    if (remove) {
        remove.addEventListener('change', () => {
            image.hidden = remove.checked;
            updateFallback(remove.checked);
            if (remove.checked) input.value = '';
        });
    }
});

const companyName = document.querySelector('#mg-name');
const companyInitials = document.querySelector('[data-mg-initials]');
if (companyName && companyInitials) {
    companyName.addEventListener('input', () => {
        const words = companyName.value.trim().split(/\s+/u).filter(Boolean).slice(0, 2);
        companyInitials.textContent = words.map((word) => Array.from(word)[0] || '').join('').toLocaleUpperCase('pl') || 'BI';
    });
}

const bookingDateForm = document.querySelector('[data-booking-date]');
if (bookingDateForm) {
    const dateInput = bookingDateForm.querySelector('[name="date"]');
    const preview = document.querySelector('[data-slot-preview]');
    let request = null;
    dateInput.addEventListener('change', async () => {
        if (!dateInput.value || !preview) return;
        if (request) request.abort();
        request = new AbortController();
        preview.replaceChildren();
        const status = document.createElement('p');
        status.textContent = 'Sprawdzamy wolne godziny…';
        preview.append(status);
        const params = new URLSearchParams({
            route: 'booking/slots', service_id: bookingDateForm.elements.service_id.value,
            employee_id: bookingDateForm.elements.employee_id.value, date: dateInput.value,
        });
        try {
            const response = await fetch(`./index.php?${params}`, { signal: request.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('request');
            const data = await response.json();
            if (data.date !== dateInput.value) return;
            preview.replaceChildren();
            const message = document.createElement('p');
            message.textContent = data.slots.length ? `Dostępne godziny: ${data.slots.map((slot) => slot.start).join(', ')}. Przejdź dalej, aby wybrać.` : 'Brak wolnych terminów tego dnia.';
            preview.append(message);
        } catch (error) {
            if (error.name === 'AbortError') return;
            preview.replaceChildren();
            const message = document.createElement('p');
            message.textContent = 'Nie udało się odświeżyć godzin. Użyj przycisku „Pokaż godziny”.';
            preview.append(message);
        }
    });
}
