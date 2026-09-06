(function () {
    // Marcador de versão: se esta linha NÃO aparecer no Console assim
    // que a página carregar, o navegador está rodando um app.js
    // DIFERENTE deste arquivo — ou seja, o problema é de deploy/cache,
    // não de código. Ajuste o texto/data a cada mudança relevante pra
    // facilitar a conferência.
    console.log('%c[app.js] versão carregada: 2026-09-01-responsive-mobile-first-v2.09.5', 'color:#0aa6bd;font-weight:bold');

    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function setupPwa() {
        if ('serviceWorker' in navigator && (window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('./service-worker.js').catch(function () {
                    // A aplicação continua funcionando mesmo sem service worker.
                });
            });
        }
    }

    function setupConfirmations() {
        qsa('[data-confirm]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                if (!window.confirm(button.getAttribute('data-confirm'))) {
                    event.preventDefault();
                }
            });
        });
    }

    function setupNetworkBanner() {
        var banner = document.createElement('div');
        banner.className = 'alert alert-error';
        banner.style.display = 'none';
        banner.textContent = 'Sem internet. As telas abertas continuam visiveis, mas novas consultas dependem de conexao.';

        var shell = qs('.shell');
        if (!shell) {
            return;
        }

        shell.insertBefore(banner, shell.firstChild);

        function update() {
            banner.style.display = navigator.onLine ? 'none' : 'block';
        }

        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        update();
    }

    function renderSlots(container, slots, hiddenInput, submitButton) {
        container.innerHTML = '';
        hiddenInput.value = '';
        if (submitButton) {
            submitButton.disabled = true;
        }

        if (!slots.length) {
            var empty = document.createElement('p');
            empty.className = 'muted';
            empty.textContent = 'Nenhum horario livre para esta data.';
            container.appendChild(empty);
            return;
        }

        slots.forEach(function (slot) {
            var label = document.createElement('label');
            var input = document.createElement('input');
            var span = document.createElement('span');

            input.type = 'radio';
            input.name = 'slot_choice';
            input.value = String(slot.id);
            span.textContent = slot.label;

            input.addEventListener('change', function () {
                hiddenInput.value = input.value;
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });

            label.appendChild(input);
            label.appendChild(span);
            container.appendChild(label);
        });
    }

    function setupRolePicker() {
        var picker = qs('.role-picker');
        if (!picker) {
            return;
        }
        var hiddenInput = qs('#role_context');
        var buttons = qsa('.role-option', picker);

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                buttons.forEach(function (b) { b.classList.remove('active'); });
                button.classList.add('active');
                if (hiddenInput) {
                    hiddenInput.value = button.getAttribute('data-role');
                }
            });
        });
    }

    function setupRoleSwitches() {
        qsa('[data-role-switch]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var form = checkbox.closest('form');
                if (form) {
                    form.submit();
                }
            });
        });
    }

    /**
     * Menu hambúrguer (mobile/tablet, < 1024px): abre/fecha a gaveta de
     * navegação, fecha automaticamente ao navegar ou ao clicar fora, e
     * mantém o atributo aria-expanded sincronizado para acessibilidade.
     * Em telas >= 1024px o CSS já força o menu visível (ver styles.css),
     * então este JS não interfere no desktop.
     */
    function setupMobileNav() {
        var toggle = qs('[data-nav-toggle]');
        var nav = qs('[data-nav]');
        if (!toggle || !nav) {
            return;
        }

        function closeNav() {
            nav.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        function toggleNav() {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            toggleNav();
        });

        qsa('a', nav).forEach(function (link) {
            link.addEventListener('click', closeNav);
        });

        document.addEventListener('click', function (event) {
            if (!nav.contains(event.target) && !toggle.contains(event.target)) {
                closeNav();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNav();
            }
        });

        // Se a tela for redimensionada para desktop com o menu aberto no
        // mobile, garante que o estado não fique "preso" ao voltar pro
        // mobile depois.
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) {
                closeNav();
            }
        });
    }

    /**
     * Ativa o modo "cartão empilhado" das tabelas em telas pequenas
     * (< 640px, ver styles.css): copia o texto de cada <th> para um
     * atributo data-label na célula correspondente, sem precisar tocar
     * em nenhuma página PHP que renderiza tabelas. Em telas >= 640px o
     * CSS ignora essa classe e a tabela usa a rolagem horizontal normal
     * (.table-wrap { overflow-x: auto }).
     */
    function setupResponsiveTables() {
        qsa('.table-wrap table').forEach(function (table) {
            var headers = qsa('thead th', table).map(function (th) {
                return th.textContent.trim();
            });
            if (!headers.length) {
                return;
            }
            table.classList.add('responsive-cards');
            qsa('tbody tr', table).forEach(function (row) {
                qsa('td', row).forEach(function (cell, index) {
                    if (headers[index]) {
                        cell.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    }

    /**
     * Tutorial de primeiro acesso (modal com passos "Anterior/Próximo/
     * Concluir"). O PHP só desenha o <dialog> quando
     * users.tutorial_seen = 0 — aqui só cuidamos da navegação entre os
     * passos e de avisar o servidor quando o usuário termina ou pula,
     * para o modal nunca mais aparecer para essa conta.
     */
    /**
     * Tutorial de primeiro acesso — tour guiado: em vez de um modal com
     * texto solto, cada passo destaca (com uma "luz" ao redor) o item
     * de menu real que está sendo explicado, e um balão de texto aparece
     * ao lado dele. Passos sem alvo (a boas-vindas inicial) aparecem
     * centralizados na tela.
     */
    /**
     * Aceite obrigatório dos Termos de Uso/Privacidade no primeiro
     * acesso. Diferente do tutorial, este modal é bloqueante de
     * verdade: sem X, sem Esc, sem clique fora — só libera com a caixa
     * marcada + "Prosseguir". Enquanto estiver visível, trava também a
     * rolagem da página por trás dele.
     */
    function setupTermsGate() {
        var modal = qs('[data-terms-modal]');
        var overlay = qs('[data-terms-overlay]');
        if (!modal || !overlay) {
            return;
        }

        var form = qs('[data-terms-form]', modal);
        var checkbox = qs('[data-terms-checkbox]', modal);
        var agreeValue = qs('[data-terms-agree-value]', modal);
        var submitButton = qs('[data-terms-submit]', modal);

        document.documentElement.style.overflow = 'hidden';

        checkbox.addEventListener('change', function () {
            submitButton.disabled = !checkbox.checked;
            agreeValue.value = checkbox.checked ? '1' : '0';
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!checkbox.checked) {
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = 'Enviando...';

            fetch(window.location.href, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) {
                    return response.json().then(function (data) {
                        return { ok: response.ok, data: data };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data || result.data.success === false) {
                        throw new Error((result.data && result.data.flash && result.data.flash.message) || 'Não foi possível registrar o aceite.');
                    }
                    // Recarrega a página: o servidor agora sabe que os
                    // termos foram aceitos, então o modal não é mais
                    // desenhado no próximo carregamento.
                    document.documentElement.style.overflow = '';
                    window.location.reload();
                })
                .catch(function (error) {
                    console.error('[termos] falha ao registrar aceite:', error);
                    alert('Não foi possível registrar seu aceite agora. Tente novamente.');
                    submitButton.disabled = false;
                    submitButton.textContent = 'Prosseguir';
                });
        });
    }

    function setupTutorial() {
        var dataScript = document.getElementById('tutorial-steps-data');
        var tooltip = qs('[data-tour-tooltip]');
        var highlight = qs('[data-tour-highlight]');
        var overlay = qs('[data-tour-overlay]');
        if (!dataScript || !tooltip || !highlight || !overlay) {
            return;
        }

        var steps;
        try {
            steps = JSON.parse(dataScript.textContent);
        } catch (error) {
            console.error('[tutorial] dados dos passos inválidos:', error);
            return;
        }
        if (!steps || !steps.length) {
            return;
        }

        var titleEl = qs('[data-tour-title]', tooltip);
        var textEl = qs('[data-tour-text]', tooltip);
        var dots = qsa('.tutorial-dot', tooltip);
        var prevButton = qs('[data-tutorial-prev]', tooltip);
        var nextButton = qs('[data-tutorial-next]', tooltip);
        var skipButtons = qsa('[data-tutorial-skip]', tooltip);
        var csrfInput = qs('[data-tutorial-csrf]', tooltip);
        var nav = qs('[data-nav]');
        var current = 0;
        var navWasOpen = nav ? nav.classList.contains('is-open') : false;

        function findTargetElement(pageKey) {
            if (!pageKey || !nav) {
                return null;
            }
            return nav.querySelector('a[href*="page=' + pageKey + '"]');
        }

        function positionCentered() {
            highlight.hidden = true;
            tooltip.style.transform = 'translate(-50%, -50%)';
            tooltip.style.top = '50%';
            tooltip.style.left = '50%';
        }

        function positionNear(target) {
            var rect = target.getBoundingClientRect();
            var padding = 6;

            highlight.hidden = false;
            highlight.style.top = (rect.top - padding) + 'px';
            highlight.style.left = (rect.left - padding) + 'px';
            highlight.style.width = (rect.width + padding * 2) + 'px';
            highlight.style.height = (rect.height + padding * 2) + 'px';

            // Tenta posicionar o balão abaixo do item destacado; se não
            // couber (perto do fim da tela), posiciona acima dele.
            var tooltipHeight = tooltip.offsetHeight || 220;
            var spaceBelow = window.innerHeight - rect.bottom;
            var top = spaceBelow > tooltipHeight + 24
                ? rect.bottom + 14
                : Math.max(14, rect.top - tooltipHeight - 14);
            var left = Math.min(
                Math.max(14, rect.left),
                window.innerWidth - tooltip.offsetWidth - 14
            );

            tooltip.style.transform = 'none';
            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
        }

        function reposition() {
            var step = steps[current];
            var target = findTargetElement(step.target);
            if (target) {
                target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                positionNear(target);
            } else {
                positionCentered();
            }
        }

        function showStep(index) {
            current = index;
            var step = steps[index];

            titleEl.textContent = step.title;
            textEl.textContent = step.text;
            dots.forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === index);
            });
            prevButton.disabled = index === 0;
            nextButton.textContent = index === steps.length - 1 ? 'Concluir' : 'Próximo';

            // Passos com alvo no menu precisam do menu aberto — no
            // mobile/tablet ele começa escondido dentro da gaveta
            // hambúrguer (ver setupMobileNav).
            if (step.target && nav && !nav.classList.contains('is-open')) {
                nav.classList.add('is-open');
            }

            // Espera o layout assentar (ex.: menu acabou de abrir) antes
            // de medir a posição real do elemento-alvo na tela.
            requestAnimationFrame(reposition);
        }

        function finish() {
            var body = new FormData();
            body.append('action', 'mark_tutorial_seen');
            body.append('csrf_token', csrfInput ? csrfInput.value : '');
            fetch(window.location.href, {
                method: 'POST',
                body: body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            }).catch(function (error) {
                console.error('[tutorial] falha ao registrar conclusão:', error);
            });

            overlay.hidden = true;
            highlight.hidden = true;
            tooltip.hidden = true;
            window.removeEventListener('resize', reposition);
            window.removeEventListener('scroll', reposition, true);
            if (nav && !navWasOpen) {
                nav.classList.remove('is-open');
            }
        }

        nextButton.addEventListener('click', function () {
            if (current === steps.length - 1) {
                finish();
                return;
            }
            showStep(current + 1);
        });

        prevButton.addEventListener('click', function () {
            if (current > 0) {
                showStep(current - 1);
            }
        });

        skipButtons.forEach(function (button) {
            button.addEventListener('click', finish);
        });

        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, true);

        overlay.hidden = false;
        tooltip.hidden = false;
        showStep(0);
    }

    function setupModals() {
        qsa('[data-open-modal]').forEach(function (trigger) {
            var modal = document.getElementById(trigger.getAttribute('data-open-modal'));
            if (!modal) {
                return;
            }
            trigger.addEventListener('click', function () {
                modal.showModal();
            });
        });

        qsa('dialog.modal').forEach(function (modal) {
            qsa('[data-close-modal]', modal).forEach(function (closeButton) {
                closeButton.addEventListener('click', function () {
                    modal.close();
                });
            });
            // Fecha ao clicar fora da caixa (no backdrop do <dialog>).
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.close();
                }
            });
        });
    }

    /**
     * Calendário visual do modal "Nova consulta": substitui o
     * <input type="date"> nativo por uma grade de dias clicável, ao
     * lado dos outros campos, e esmaece os dias em que o médico
     * escolhido não tem agenda cadastrada (ver doctor_working_weekdays()
     * em app/repository.php e a ação "doctor_weekdays" em index.php).
     * O input escondido #new-appointment-date continua existindo e
     * recebendo um evento "change" normal a cada clique — é assim que
     * setupSlots() (que não foi alterado) continua funcionando sem
     * precisar saber que a data agora vem de um calendário, e não mais
     * de um <input type="date"> nativo.
     */
    function setupAppointmentCalendar() {
        var MONTHS = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        qsa('[data-appt-calendar]').forEach(function (calendar) {
            var form = calendar.closest('form');
            var dateInput = qs('#new-appointment-date', calendar);
            var label = qs('[data-cal-label]', calendar);
            var daysBox = qs('[data-cal-days]', calendar);
            var hint = qs('[data-cal-hint]', calendar);
            var prevButton = qs('[data-cal-prev]', calendar);
            var nextButton = qs('[data-cal-next]', calendar);
            var doctorInput = form ? qs('[data-appointment-doctor]', form) : null;
            if (!dateInput || !daysBox || !doctorInput) {
                return;
            }

            var minDate = calendar.getAttribute('data-min-date'); // 'YYYY-MM-DD'
            var maxDays = parseInt(calendar.getAttribute('data-max-days'), 10) || 60;
            var minDateObj = new Date(minDate + 'T00:00:00');
            var maxDateObj = new Date(minDateObj.getTime());
            maxDateObj.setDate(maxDateObj.getDate() + maxDays);

            var viewMonth = new Date(minDateObj.getFullYear(), minDateObj.getMonth(), 1);
            var selectedDate = null;
            var workingWeekdays = null; // null = nenhum médico selecionado ainda

            function toIso(date) {
                var y = date.getFullYear();
                var m = String(date.getMonth() + 1).padStart(2, '0');
                var d = String(date.getDate()).padStart(2, '0');
                return y + '-' + m + '-' + d;
            }

            function render() {
                label.textContent = MONTHS[viewMonth.getMonth()] + ' de ' + viewMonth.getFullYear();

                var firstWeekday = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), 1).getDay();
                var daysInMonth = new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 0).getDate();

                var html = '';
                for (var i = 0; i < firstWeekday; i++) {
                    html += '<span class="appointment-calendar-blank"></span>';
                }
                for (var day = 1; day <= daysInMonth; day++) {
                    var dayDate = new Date(viewMonth.getFullYear(), viewMonth.getMonth(), day);
                    var iso = toIso(dayDate);
                    var weekday = dayDate.getDay();

                    var outOfRange = dayDate < minDateObj || dayDate > maxDateObj;
                    var offDay = workingWeekdays !== null && workingWeekdays.indexOf(weekday) === -1;
                    var disabled = outOfRange || offDay || workingWeekdays === null;
                    var classes = ['appointment-calendar-day'];
                    if (disabled) classes.push('is-disabled');
                    if (iso === selectedDate) classes.push('is-selected');

                    html += '<button type="button" class="' + classes.join(' ') + '" data-cal-date="' + iso + '"' + (disabled ? ' disabled' : '') + '>' + day + '</button>';
                }
                daysBox.innerHTML = html;

                qsa('[data-cal-date]', daysBox).forEach(function (button) {
                    button.addEventListener('click', function () {
                        selectedDate = button.getAttribute('data-cal-date');
                        dateInput.value = selectedDate;
                        dateInput.dispatchEvent(new Event('change', { bubbles: true }));
                        render();
                    });
                });

                prevButton.disabled = viewMonth.getFullYear() === minDateObj.getFullYear() && viewMonth.getMonth() === minDateObj.getMonth();
            }

            prevButton.addEventListener('click', function () {
                viewMonth.setMonth(viewMonth.getMonth() - 1);
                render();
            });
            nextButton.addEventListener('click', function () {
                viewMonth.setMonth(viewMonth.getMonth() + 1);
                render();
            });

            doctorInput.addEventListener('change', function () {
                selectedDate = null;
                dateInput.value = '';
                dateInput.dispatchEvent(new Event('change', { bubbles: true }));

                var doctorId = doctorInput.value;
                if (!doctorId) {
                    workingWeekdays = null;
                    hint.textContent = 'Selecione o médico para ver os dias de atendimento.';
                    render();
                    return;
                }

                hint.textContent = 'Carregando dias de atendimento...';
                fetch('index.php?action=doctor_weekdays&doctor_id=' + encodeURIComponent(doctorId), {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        workingWeekdays = data.weekdays || [];
                        hint.textContent = workingWeekdays.length
                            ? 'Dias em destaque: quando esse médico atende. Clique em um dia para ver os horários.'
                            : 'Este médico ainda não tem agenda semanal cadastrada.';
                        render();
                    })
                    .catch(function () {
                        workingWeekdays = [];
                        hint.textContent = 'Não foi possível carregar a agenda deste médico agora.';
                        render();
                    });
            });

            render();
        });
    }

    function setupSlots() {
        qsa('[data-slot-loader]').forEach(function (container) {
            var form = container.closest('form');
            var hiddenInput = form ? qs('[data-selected-slot]', form) : null;
            var submitButton = form ? qs('button[type="submit"]', form) : null;
            var dateInput = document.getElementById(container.getAttribute('data-date-input'));
            var doctorSelect = form ? qs('[data-appointment-doctor]', form) : null;
            var staticDoctorId = container.getAttribute('data-doctor-id');

            if (!hiddenInput || !dateInput || (!doctorSelect && !staticDoctorId)) {
                return;
            }

            function currentDoctorId() {
                return doctorSelect ? doctorSelect.value : staticDoctorId;
            }

            function load() {
                var date = dateInput.value;
                var doctorId = currentDoctorId();
                if (!date || !doctorId) {
                    container.innerHTML = '<span class="muted">Selecione o médico e a data para ver os horários.</span>';
                    hiddenInput.value = '';
                    return;
                }

                container.innerHTML = '<span class="muted">Carregando horarios...</span>';
                hiddenInput.value = '';
                if (submitButton) {
                    submitButton.disabled = true;
                }

                fetch('index.php?action=slots&doctor_id=' + encodeURIComponent(doctorId) + '&date=' + encodeURIComponent(date), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Falha ao buscar horarios.');
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        renderSlots(container, data.slots || [], hiddenInput, submitButton);
                    })
                    .catch(function () {
                        container.innerHTML = '<p class="muted">Nao foi possivel carregar os horarios agora.</p>';
                    });
            }

            dateInput.addEventListener('change', load);
            if (doctorSelect) {
                doctorSelect.addEventListener('change', load);
            }
            form.addEventListener('submit', function (event) {
                if (!hiddenInput.value) {
                    event.preventDefault();
                    window.alert('Selecione um horario livre.');
                }
            });
            if (currentDoctorId() && dateInput.value) {
                load();
            }
        });
    }

    /**
     * Campo de busca com sugestões (autocomplete) para seleção de
     * Paciente/Médico no formulário de nova consulta. Faz a filtragem
     * localmente, sobre a lista de opções já embutida na página em
     * <script type="application/json">, sem precisar de requisição
     * extra ao servidor a cada letra digitada.
     */
    function setupAutocomplete() {
        qsa('[data-autocomplete]').forEach(function (wrapper) {
            var searchInput = qs('[data-autocomplete-search]', wrapper);
            var valueInput = qs('[data-autocomplete-value]', wrapper);
            var list = qs('[data-autocomplete-list]', wrapper);
            var optionsScript = qs('[data-autocomplete-options]', wrapper);
            var form = wrapper.closest('form');

            if (!searchInput || !valueInput || !list || !optionsScript) {
                return;
            }

            var options = [];
            try {
                options = JSON.parse(optionsScript.textContent || '[]');
            } catch (parseError) {
                options = [];
            }

            var filtered = [];
            var highlighted = -1;

            function closeList() {
                list.hidden = true;
                list.innerHTML = '';
                highlighted = -1;
            }

            function selectOption(option) {
                valueInput.value = option.id;
                searchInput.value = option.label;
                closeList();
                // Definir .value por JavaScript NÃO dispara o evento
                // "change" sozinho — precisamos disparar manualmente,
                // já que o carregador de horários do médico
                // (setupSlots) escuta esse evento para recarregar os
                // horários disponíveis assim que um médico é escolhido.
                valueInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function renderList() {
                list.innerHTML = '';

                if (filtered.length === 0) {
                    var empty = document.createElement('li');
                    empty.className = 'autocomplete-empty';
                    empty.textContent = 'Nenhum resultado encontrado.';
                    list.appendChild(empty);
                    list.hidden = false;
                    return;
                }

                filtered.forEach(function (option, index) {
                    var item = document.createElement('li');
                    item.className = 'autocomplete-item' + (index === highlighted ? ' is-active' : '');
                    item.setAttribute('role', 'option');

                    var title = document.createElement('strong');
                    title.textContent = option.label;
                    item.appendChild(title);

                    if (option.sub) {
                        var sub = document.createElement('span');
                        sub.className = 'muted';
                        sub.textContent = option.sub;
                        item.appendChild(sub);
                    }

                    // "mousedown" (em vez de "click") garante que a
                    // seleção seja registrada ANTES do evento "blur"
                    // do campo de texto fechar a lista.
                    item.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                        selectOption(option);
                    });

                    list.appendChild(item);
                });

                list.hidden = false;
            }

            function search(term) {
                var normalized = term.trim().toLowerCase();
                if (!normalized) {
                    filtered = [];
                    closeList();
                    return;
                }
                filtered = options
                    .filter(function (option) {
                        return option.terms.indexOf(normalized) !== -1;
                    })
                    .slice(0, 8);
                highlighted = filtered.length ? 0 : -1;
                renderList();
            }

            searchInput.addEventListener('input', function () {
                if (valueInput.value) {
                    valueInput.value = '';
                    valueInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                search(searchInput.value);
            });

            searchInput.addEventListener('keydown', function (event) {
                if (list.hidden) {
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    highlighted = Math.min(highlighted + 1, filtered.length - 1);
                    renderList();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    highlighted = Math.max(highlighted - 1, 0);
                    renderList();
                } else if (event.key === 'Enter') {
                    if (highlighted >= 0 && filtered[highlighted]) {
                        event.preventDefault();
                        selectOption(filtered[highlighted]);
                    }
                } else if (event.key === 'Escape') {
                    closeList();
                }
            });

            searchInput.addEventListener('blur', function () {
                window.setTimeout(closeList, 100);
            });
            // A validação de "opção realmente selecionada" foi centralizada
            // em setupNewAppointmentForm() — manter também aqui causava
            // MÚLTIPLOS listeners de "submit" concorrentes no mesmo
            // formulário (um por campo de autocomplete + o do horário +
            // o novo, unificado), o que gerava comportamento
            // inconsistente (às vezes nenhum feedback aparecia).
        });
    }

    /**
     * Cria (uma vez só) a "pilha" de toasts flutuantes no canto da tela,
     * reaproveitada por qualquer chamada de showToast().
     */
    function toastStack() {
        var stack = qs('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        return stack;
    }

    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + (type === 'error' ? 'error' : 'success');
        toast.textContent = message;
        toastStack().appendChild(toast);
        window.setTimeout(function () {
            toast.remove();
        }, 4000);
    }

    /**
     * Busca a página atual de novo (sem navegar pra ela) e substitui só
     * o painel da tabela de consultas pelo conteúdo novo — é assim que
     * a lista se atualiza sem precisar de F5 manual.
     */
    function refreshAppointmentsPanel() {
        var current = qs('[data-appointments-panel]');
        if (!current) {
            return;
        }
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest-Fragment' } })
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var fresh = doc.querySelector('[data-appointments-panel]');
                if (fresh) {
                    current.replaceWith(fresh);
                    // A tabela recém-inserida ainda não passou pelo
                    // realce de acessibilidade/mobile do setup inicial.
                    setupResponsiveTables();
                }
            })
            .catch(function (error) {
                console.error('[nova-consulta] falha ao atualizar a lista', error);
                // Se a atualização "silenciosa" falhar, o pior caso é a
                // lista ficar desatualizada até o próximo F5 — não é
                // motivo pra mostrar erro ao usuário, já a consulta em
                // si já foi salva com sucesso nesse ponto.
            });
    }

    /**
     * Validação, envio via AJAX e feedback do formulário "Adicionar Nova
     * Consulta". Centraliza num único lugar a checagem dos 3 campos que
     * dependem de seleção (paciente, médico e horário), o disparo da
     * requisição, o estado de carregamento do botão e o toast final —
     * evitando o reload de página tradicional.
     */
    /**
     * Tela de Relatórios: monta um gráfico (Chart.js) com consultas x
     * realizadas x faltas de cada médico, revela a versão "para
     * impressão" da página (só some quando @media print entra em
     * ação, ver styles.css) e abre o diálogo de impressão do
     * navegador. Só existe nesta tela — se os elementos não existirem
     * na página atual, a função não faz nada.
     */
    /**
     * Card "Movimentação mensal" da tela de Relatórios: mantém um
     * gráfico (Consultas x Faltas) sempre visível, que já nasce
     * preenchido com o mês atual (dados injetados pelo servidor em
     * [data-movement-initial], sem precisar de uma chamada extra ao
     * carregar a página). O botão "Atualizar gráfico" busca
     * (?action=monthly_movement) os dados do mês escolhido no seletor
     * e redesenha o mesmo gráfico, sem recarregar a tela.
     */
    function setupMovementChart() {
        var canvas = qs('[data-movement-chart-canvas]');
        var badge = qs('[data-movement-badge]');
        var monthInput = qs('[data-movement-month]');
        var refreshButton = qs('[data-refresh-movement-chart]');
        var printButton = qs('[data-print-movement]');
        var initialScript = qs('[data-movement-initial]');
        if (!canvas || !badge || !monthInput || !refreshButton || typeof Chart === 'undefined') {
            return;
        }

        var chartInstance = null;
        var printChartInstance = null;
        var lastData = null; // sempre os dados do último mês carregado

        function classLabel(classification) {
            return {
                high: 'Alta movimentação',
                good: 'Boa movimentação',
                low: 'Baixa movimentação',
            }[classification] || classification;
        }

        function classColor(classification) {
            return {
                high: '#047857',
                good: '#0aa6bd',
                low: '#b54708',
            }[classification] || '#5d7190';
        }

        function render(data) {
            lastData = data;
            badge.textContent = data.classification_label + ' — ' + data.total + ' consultas, ' + data.no_shows + ' faltas';
            badge.className = 'movement-badge movement-' + data.classification;

            if (chartInstance) {
                chartInstance.destroy();
            }
            chartInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: ['Consultas', 'Faltas'],
                    datasets: [{
                        label: data.month_label || '',
                        data: [data.total, data.no_shows],
                        backgroundColor: [classColor(data.classification), '#b42318'],
                    }],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: classLabel(data.classification) + ' (' + (data.month_label || '') + ')' },
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                    },
                },
            });
        }

        // Primeiro desenho: usa os dados que o próprio PHP já calculou
        // pro mês atual, sem precisar de fetch() nenhum.
        if (initialScript) {
            try {
                render(JSON.parse(initialScript.textContent));
            } catch (error) {
                console.error('[relatorios] dados iniciais de movimentação inválidos:', error);
            }
        }

        function loadMonth(month) {
            return fetch('index.php?action=monthly_movement&month=' + encodeURIComponent(month), {
                headers: { 'Accept': 'application/json' },
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Falha ao buscar a movimentação do mês.');
                }
                return response.json();
            });
        }

        refreshButton.addEventListener('click', function () {
            var month = monthInput.value; // "YYYY-MM"
            if (!month) {
                window.alert('Escolha um mês.');
                return;
            }

            refreshButton.disabled = true;
            var originalText = refreshButton.textContent;
            refreshButton.textContent = 'Atualizando...';

            loadMonth(month)
                .then(render)
                .catch(function (error) {
                    console.error('[relatorios] falha ao atualizar gráfico de movimentação:', error);
                    window.alert('Não foi possível atualizar o gráfico agora.');
                })
                .finally(function () {
                    refreshButton.disabled = false;
                    refreshButton.textContent = originalText;
                });
        });

        // Botão "Imprimir relatório": sempre imprime o MESMO mês que
        // está sendo mostrado no gráfico da tela (lastData) — se o mês
        // selecionado ainda não tiver sido carregado (usuário trocou o
        // seletor mas não clicou em "Atualizar gráfico"), busca antes
        // de imprimir, pra nunca imprimir um mês desatualizado.
        if (printButton) {
            var printSection = qs('[data-print-report]');
            var printPeriod = qs('[data-print-period]', printSection);
            var printTotal = qs('[data-print-total]', printSection);
            var printNoShows = qs('[data-print-noshows]', printSection);
            var printDoctorCount = qs('[data-print-doctor-count]', printSection);
            var printCanvas = qs('[data-print-chart-canvas]', printSection);
            var printTbody = qs('[data-print-tbody]', printSection);

            function fillPrintReport(data) {
                printPeriod.textContent = 'Período: ' + (data.period_label || data.month_label || '');
                printTotal.textContent = data.total;
                printNoShows.textContent = data.no_shows;
                printDoctorCount.textContent = data.doctors ? data.doctors.length : 0;

                printTbody.innerHTML = '';
                (data.doctors || []).forEach(function (doctor) {
                    var row = document.createElement('tr');
                    row.innerHTML =
                        '<td></td><td></td><td></td><td></td>';
                    row.children[0].textContent = doctor.name;
                    row.children[1].textContent = doctor.total;
                    row.children[2].textContent = doctor.completed;
                    row.children[3].textContent = doctor.no_shows;
                    printTbody.appendChild(row);
                });

                if (printChartInstance) {
                    printChartInstance.destroy();
                }
                var labels = (data.doctors || []).map(function (d) { return d.name; });
                printChartInstance = new Chart(printCanvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Consultas', data: (data.doctors || []).map(function (d) { return d.total; }), backgroundColor: '#0aa6bd' },
                            { label: 'Realizadas', data: (data.doctors || []).map(function (d) { return d.completed; }), backgroundColor: '#047857' },
                            { label: 'Faltas', data: (data.doctors || []).map(function (d) { return d.no_shows; }), backgroundColor: '#b42318' },
                        ],
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: 'Consultas por médico — ' + (data.month_label || '') },
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } },
                        },
                    },
                });
            }

            printButton.addEventListener('click', function () {
                var month = monthInput.value;
                var needsFetch = !lastData || (month && lastData.month && lastData.month !== month);

                var ready = needsFetch && month ? loadMonth(month) : Promise.resolve(lastData);

                printButton.disabled = true;
                var originalText = printButton.textContent;
                printButton.textContent = 'Preparando...';

                ready
                    .then(function (data) {
                        if (needsFetch) {
                            render(data); // também atualiza o gráfico visível na tela
                        }
                        fillPrintReport(data);

                        // Espera o(s) gráfico(s) terminar(em) de desenhar
                        // antes de abrir a impressão.
                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () {
                                window.print();
                            });
                        });
                    })
                    .catch(function (error) {
                        console.error('[relatorios] falha ao preparar impressão:', error);
                        window.alert('Não foi possível preparar o relatório para impressão agora.');
                    })
                    .finally(function () {
                        printButton.disabled = false;
                        printButton.textContent = originalText;
                    });
            });
        }
    }

    /**
     * O Chart.js só redesenha um gráfico em alta definição quando
     * percebe uma mudança de tamanho — e trocar de tela para papel
     * (@media print) não dispara isso sozinho. Sem isto, o gráfico
     * impresso sairia borrado/espremido no tamanho que tinha na tela.
     * Chart.instances é um registro que a própria biblioteca mantém de
     * todo gráfico já criado na página.
     */
    window.addEventListener('beforeprint', function () {
        if (typeof Chart === 'undefined' || !Chart.instances) {
            return;
        }
        Object.keys(Chart.instances).forEach(function (key) {
            Chart.instances[key].resize();
        });
    });

    function setupNewAppointmentForm() {
        var form = document.querySelector('#new-appointment-modal form');
        if (!form) {
            return;
        }

        var modal = document.getElementById('new-appointment-modal');
        var patientInput = qs('input[name="patient_id"]', form);
        var doctorInput = qs('input[name="doctor_id"]', form);
        var slotInput = qs('[data-selected-slot]', form);
        var submitButton = qs('button[type="submit"]', form);

        var errorBox = document.createElement('p');
        errorBox.className = 'alert alert-error';
        errorBox.style.display = 'none';
        form.insertBefore(errorBox, form.firstChild);

        function showError(message) {
            errorBox.textContent = message;
            errorBox.style.display = 'block';
        }

        function hideError() {
            errorBox.style.display = 'none';
        }

        function setLoading(isLoading) {
            if (!submitButton) {
                return;
            }
            submitButton.disabled = isLoading;
            submitButton.textContent = isLoading ? 'Agendando...' : 'Agendar consulta';
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            try {
                var faltando = [];
                if (!patientInput || !patientInput.value) {
                    faltando.push('paciente');
                }
                if (!doctorInput || !doctorInput.value) {
                    faltando.push('médico');
                }
                if (!slotInput || !slotInput.value) {
                    faltando.push('horário');
                }

                console.debug('[nova-consulta] tentativa de envio', {
                    patient_id: patientInput ? patientInput.value : null,
                    doctor_id: doctorInput ? doctorInput.value : null,
                    slot_id: slotInput ? slotInput.value : null,
                    faltando: faltando,
                });

                if (faltando.length > 0) {
                    showError('Antes de agendar, selecione: ' + faltando.join(', ') + '.');
                    return;
                }

                hideError();
                setLoading(true);

                // IMPORTANTE: não usar "form.action" aqui — como o
                // formulário tem um campo <input name="action">, o
                // navegador substitui a propriedade nativa form.action
                // pelo próprio elemento <input> (em vez da URL), e o
                // fetch() tentava enviar para "[object HTMLInputElement]"
                // (gerando 404). Por isso usamos sempre a URL atual.
                fetch(window.location.href, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (response) {
                        return response.text().then(function (rawText) {
                            var data;
                            try {
                                data = JSON.parse(rawText);
                            } catch (parseError) {
                                // O servidor respondeu algo que NÃO é JSON —
                                // geralmente um aviso/warning do PHP impresso
                                // antes do json_encode(), ou uma página de
                                // erro HTML. Mostramos o conteúdo bruto no
                                // console pra dar visibilidade real do que
                                // quebrou, em vez de um erro genérico.
                                console.error('[nova-consulta] resposta do servidor não é JSON válido:', rawText);
                                throw new Error('O servidor respondeu algo inesperado (não era JSON). Veja o Console (F12) para o conteúdo completo.');
                            }
                            return { ok: response.ok, status: response.status, data: data };
                        });
                    })
                    .then(function (result) {
                        setLoading(false);

                        if (!result.ok) {
                            var mensagem = (result.data && result.data.message)
                                || 'Não foi possível agendar a consulta (erro ' + result.status + ').';
                            showError(mensagem);
                            console.error('[nova-consulta] backend recusou o envio', result);
                            return;
                        }

                        hideError();
                        if (modal) {
                            modal.close();
                        }
                        var mensagemSucesso = (result.data.flash && result.data.flash.length)
                            ? result.data.flash[result.data.flash.length - 1].message
                            : 'Consulta agendada com sucesso.';
                        showToast(mensagemSucesso, 'success');
                        refreshAppointmentsPanel();
                        form.reset();
                        if (patientInput) { patientInput.value = ''; }
                        if (doctorInput) {
                            doctorInput.value = '';
                            // Dispara "change" pra o calendário do modal (ver
                            // setupAppointmentCalendar) também resetar sua
                            // própria seleção de dia/dias-de-atendimento —
                            // senão ele ficaria mostrando o médico/data da
                            // consulta anterior na próxima vez que o modal
                            // abrir.
                            doctorInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        if (slotInput) { slotInput.value = ''; }
                    })
                    .catch(function (error) {
                        setLoading(false);
                        console.error('[nova-consulta] falha ao enviar', error);
                        showError(error && error.message
                            ? error.message
                            : 'Falha de conexão ao tentar agendar. Verifique sua internet e tente novamente.');
                    });
            } catch (error) {
                console.error('[nova-consulta] erro inesperado ao validar o envio', error);
                setLoading(false);
                showError('Ocorreu um erro inesperado. Veja o console do navegador (F12) para detalhes.');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupPwa();
        setupConfirmations();
        setupNetworkBanner();
        setupRolePicker();
        setupRoleSwitches();
        setupMobileNav();
        setupResponsiveTables();
        setupTermsGate();
        setupTutorial();
        setupModals();
        setupAppointmentCalendar();
        setupSlots();
        setupAutocomplete();
        setupNewAppointmentForm();
        setupMovementChart();
    });
})();