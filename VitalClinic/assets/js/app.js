(function () {
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

    function setupOrientationWarning() {
        var overlay = document.createElement('div');
        overlay.className = 'orientation-lock';
        overlay.textContent = 'Modo retrato apenas.';
        document.body.appendChild(overlay);

        function update() {
            var landscapePhone = window.innerWidth < 920 && window.innerWidth > window.innerHeight;
            overlay.style.display = landscapePhone ? 'grid' : 'none';
        }

        window.addEventListener('resize', update);
        window.addEventListener('orientationchange', update);
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

            // Validação: garante que o ID selecionado (não só o texto
            // digitado) esteja preenchido antes de enviar o formulário
            // — evita salvar uma consulta com paciente/médico "digitado
            // mas não escolhido de verdade" na lista.
            if (form) {
                form.addEventListener('submit', function (event) {
                    if (!valueInput.value) {
                        event.preventDefault();
                        searchInput.focus();
                        window.alert('Selecione uma opção da lista em "' + (searchInput.placeholder || 'campo de busca') + '".');
                    }
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupPwa();
        setupConfirmations();
        setupNetworkBanner();
        setupOrientationWarning();
        setupRolePicker();
        setupRoleSwitches();
        setupModals();
        setupSlots();
        setupAutocomplete();
    });
})();
