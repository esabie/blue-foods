/**
 * Public site: cart drawer open/close and smooth add-to-cart (no full reload).
 */

function openCart() {
    const drawer = document.getElementById('cart-drawer');
    if (!drawer) return;
    drawer.dataset.open = 'true';
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
}

function closeCart() {
    const drawer = document.getElementById('cart-drawer');
    if (!drawer) return;
    drawer.dataset.open = 'false';
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
}

/** Open after the next paint so the CSS slide-in can run. */
function openCartAnimated() {
    const drawer = document.getElementById('cart-drawer');
    if (!drawer) return;
    drawer.dataset.open = 'false';
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
    requestAnimationFrame(() => {
        requestAnimationFrame(() => openCart());
    });
}

function updateCartBadge(count) {
    const button = document.querySelector('[data-cart-open]');
    if (!button) return;

    let badge = button.querySelector('[data-cart-count]');
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.dataset.cartCount = '';
            badge.className = 'rounded-full bg-amber-400 px-1.5 text-xs font-semibold text-sky-950';
            button.appendChild(badge);
        }
        badge.textContent = String(count);
    } else if (badge) {
        badge.remove();
    }
}

function applyCartResponse(data) {
    const current = document.getElementById('cart-drawer');
    if (!current || !data.html) return;

    const wasOpen = current.dataset.open === 'true';
    current.outerHTML = data.html;
    updateCartBadge(data.count ?? 0);

    if (wasOpen) {
        const drawer = document.getElementById('cart-drawer');
        if (!drawer) return;
        drawer.dataset.open = 'true';
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    } else {
        openCartAnimated();
    }
}

function isCartForm(form) {
    const action = form.getAttribute('action') || '';
    try {
        const path = new URL(action, window.location.origin).pathname;
        return path === '/cart' || path.startsWith('/cart/');
    } catch {
        return false;
    }
}

async function submitCartForm(form) {
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    const response = await fetch(form.action, {
        method,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new FormData(form),
        credentials: 'same-origin',
    });

    if (response.status === 422) {
        const payload = await response.json().catch(() => ({}));
        const message = payload.message
            || Object.values(payload.errors || {}).flat()[0]
            || 'Could not update cart.';
        window.alert(message);
        return;
    }

    if (!response.ok) {
        form.submit();
        return;
    }

    applyCartResponse(await response.json());
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-cart-open]').forEach((el) => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            openCart();
        });
    });

    document.addEventListener('click', (e) => {
        const closeEl = e.target.closest('[data-cart-close]');
        if (closeEl && document.getElementById('cart-drawer')?.contains(closeEl)) {
            e.preventDefault();
            closeCart();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeCart();
    });

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !isCartForm(form)) return;
        e.preventDefault();
        submitCartForm(form).catch(() => form.submit());
    });

    if (document.body.dataset.openCart === '1') {
        openCartAnimated();
    }
});
