(() => {
    let currentStep = Number(window.installerStep || 1);
    const notice = document.getElementById('notice');

    const message = (text = '', type = 'error') => {
        notice.textContent = text;
        notice.className = text ? `notice visible ${type}` : 'notice';
    };

    const setBusy = (button, busy, label = 'Working…') => {
        if (!button) return;
        if (busy) {
            button.dataset.label = button.innerHTML;
            button.innerHTML = `<span class="spinner"></span>${label}`;
            button.disabled = true;
        } else {
            button.innerHTML = button.dataset.label || button.innerHTML;
            button.disabled = false;
        }
    };

    const showStep = (step) => {
        currentStep = step;
        document.querySelectorAll('.step').forEach(el => el.classList.toggle('active', Number(el.dataset.step) === step));
        document.querySelectorAll('[data-step-dot]').forEach(el => {
            const number = Number(el.dataset.stepDot);
            el.classList.toggle('current', number === step);
            el.classList.toggle('complete', number < step);
            el.querySelector('span').textContent = number < step ? '✓' : number;
        });
        document.getElementById('stepNumber').textContent = step;
        document.getElementById('overallProgress').style.width = `${((step - 1) / 3) * 100}%`;
        message();
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, options);
        const body = await response.text();
        let data;
        try { data = JSON.parse(body); } catch (_) { throw new Error(body || `Server returned HTTP ${response.status}.`); }
        if (!response.ok || data.status !== 'success') throw new Error(data.message || 'The operation failed.');
        return data;
    };

    const installerTips = [
        'Please wait, your CRM is being prepared.',
        'Do not refresh or close this page.',
        'Extracting Laravel project files.',
        'Large files may take a few minutes.',
        'Preparing folders and required files.',
        'The next step will appear automatically.',
        'Laravel migrations will handle the database setup.',
        'Your CRM workspace will be ready soon.',
    ];
    const installerTip = document.getElementById('installerTip');
    let tipIndex = 0;
    let tipTimer = null;
    const startInstallerTips = () => {
        if (!installerTip || tipTimer) return;

        installerTip.textContent = installerTips[0];
        tipTimer = setInterval(() => {
            tipIndex = (tipIndex + 1) % installerTips.length;
            installerTip.classList.add('is-changing');

            setTimeout(() => {
                installerTip.textContent = installerTips[tipIndex];
                installerTip.classList.remove('is-changing');
            }, 260);
        }, 2600);
    };

    document.querySelectorAll('[data-toggle-password]').forEach(button => {
        button.addEventListener('click', () => {
            const input = button.closest('.password-field')?.querySelector('input');
            const icon = button.querySelector('i');

            if (!input || !icon) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);
            button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
    });

    document.getElementById('unzipBtn')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        setBusy(button, true, 'Extracting project…');
        message();
        startInstallerTips();
        const extractProgress = document.getElementById('extractProgress');
        const extractBar = document.getElementById('extractBar');
        const extractPercent = document.getElementById('extractPercent');
        const updateExtractProgress = (progress) => {
            const percent = Math.max(0, Math.min(100, Number(progress) || 0));
            extractBar.style.width = `${percent}%`;
            extractPercent.textContent = `${percent}%`;
        };

        extractProgress.classList.add('visible');
        updateExtractProgress(0);
        try {
            const response = await fetch('installer-config/unzip.php', { cache: 'no-store' });
            if (!response.body) throw new Error('Streaming responses are not supported by this browser.');
            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let finalEvent = null;
            while (true) {
                const { value, done } = await reader.read();
                buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';
                for (const line of lines) {
                    if (!line.trim()) continue;
                    const data = JSON.parse(line);
                    finalEvent = data;
                    if (data.progress !== undefined) updateExtractProgress(data.progress);
                    if (data.status === 'error') throw new Error(data.message);
                }
                if (done) break;
            }
            if (buffer.trim()) finalEvent = JSON.parse(buffer);
            if (!finalEvent || finalEvent.status !== 'success') throw new Error(finalEvent?.message || 'Extraction did not complete.');
            updateExtractProgress(100);
            message(finalEvent.message, 'success');
            setTimeout(() => showStep(2), 600);
        } catch (error) {
            message(error.message);
            setBusy(button, false);
        }
    });

    document.getElementById('dbForm')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        const button = form.querySelector('button');
        setBusy(button, true, 'Testing connection…');
        message();
        try {
            const data = await request('installer-config/save_db_config.php', { method: 'POST', body: new FormData(form) });
            message(data.message, 'success');
            setTimeout(() => showStep(3), 600);
        } catch (error) {
            message(error.message);
            setBusy(button, false);
        }
    });

    document.getElementById('migrateBtn')?.addEventListener('click', async event => {
        const button = event.currentTarget;
        setBusy(button, true, 'Running migrations…');
        message('Please keep this page open while Laravel prepares the database.', 'info');
        try {
            const data = await request('installer-config/import_db.php', { method: 'POST' });
            message(data.message, 'success');
            setTimeout(() => showStep(4), 600);
        } catch (error) {
            message(error.message);
            setBusy(button, false);
        }
    });

    document.getElementById('adminForm')?.addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        const button = form.querySelector('button');
        setBusy(button, true, 'Creating administrator…');
        message();
        try {
            const data = await request('installer-config/add_admin.php', { method: 'POST', body: new FormData(form) });
            message(data.message, 'success');
            setTimeout(() => window.location.assign(data.url), 900);
        } catch (error) {
            message(error.message);
            setBusy(button, false);
        }
    });
})();
