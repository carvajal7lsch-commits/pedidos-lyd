
/* SCRIPT — validación + animaciones */

(function () {
    const form = document.getElementById('loginForm');
    const card = document.getElementById('cardLogin');
    const statusBar = document.getElementById('statusBar');
    const alertaJS = document.getElementById('alertaJS');
    const alertaMsg = document.getElementById('alertaMsg');
    const inpUser = document.getElementById('usuario');
    const inpPass = document.getElementById('contrasena');
    const wrapUser = document.getElementById('wrapUsuario');
    const wrapPass = document.getElementById('wrapClave');
    const msgUser = document.getElementById('msgUsuario');
    const msgPass = document.getElementById('msgClave');
    const eyeBtn = document.getElementById('eyeBtn');
    const eyeIcon = document.getElementById('eyeIcon');

    eyeBtn.addEventListener('click', function () {
        const shown = inpPass.type === 'text';
        inpPass.type = shown ? 'password' : 'text';
        eyeIcon.className = shown ? 'bi bi-eye' : 'bi bi-eye-slash';
    });

    function setFieldState(wrap, msgEl, ok, msg) {
        wrap.classList.remove('field-ok', 'field-error');
        msgEl.textContent = msg;
        if (ok === true) { wrap.classList.add('field-ok'); msgEl.className = 'field-msg ok'; }
        if (ok === false) { wrap.classList.add('field-error'); msgEl.className = 'field-msg error'; }
        if (ok === null) { msgEl.className = 'field-msg'; }
    }
    function validateUser(show) {
        const v = inpUser.value.trim();
        if (!v) { if (show) setFieldState(wrapUser, msgUser, false, 'El usuario es obligatorio.'); return false; }
        setFieldState(wrapUser, msgUser, true, ''); return true;
    }
    function validatePass(show) {
        const v = inpPass.value;
        if (!v) { if (show) setFieldState(wrapPass, msgPass, false, 'La contraseña es obligatoria.'); return false; }
        setFieldState(wrapPass, msgPass, true, ''); return true;
    }

    function shakeCard() { card.classList.remove('shake'); void card.offsetWidth; card.classList.add('shake'); }
    function flashBorderError() { card.classList.remove('border-ok', 'border-error'); void card.offsetWidth; card.classList.add('border-error'); setTimeout(() => card.classList.remove('border-error'), 1800); }
    function flashBorderOk() { card.classList.remove('border-ok', 'border-error'); void card.offsetWidth; card.classList.add('border-ok'); }
    function showAlert(msg) { alertaMsg.textContent = msg; alertaJS.style.display = 'flex'; alertaJS.classList.remove('alerta-hide'); alertaJS.classList.add('alerta-show'); }
    function hideAlert() { alertaJS.classList.remove('alerta-show'); alertaJS.classList.add('alerta-hide'); setTimeout(() => { alertaJS.style.display = 'none'; alertaJS.classList.remove('alerta-hide'); }, 300); }

    inpUser.addEventListener('input', () => { validateUser(true); updateProgressBar(); });
    inpPass.addEventListener('input', () => { validatePass(true); updateProgressBar(); });

    form.addEventListener('submit', function (e) {
        const okU = validateUser(true);
        const okP = validatePass(true);
        if (!okU || !okP) {
            e.preventDefault(); shakeCard(); flashBorderError();
            showAlert(!okU ? 'Completa el campo de usuario.' : 'Completa el campo de contraseña.');
            updateProgressBar(); return;
        }
        flashBorderOk(); hideAlert();
        statusBar.style.width = '100%'; statusBar.style.background = '#27ae60';
    });

    if (document.getElementById('alertaError')) { shakeCard(); flashBorderError(); }

    inpUser.addEventListener('focus', () => { if (!wrapUser.classList.contains('field-error')) setFieldState(wrapUser, msgUser, null, ''); });
    inpPass.addEventListener('focus', () => { if (!wrapPass.classList.contains('field-error')) setFieldState(wrapPass, msgPass, null, ''); });
})();
