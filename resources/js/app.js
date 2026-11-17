import './bootstrap';
import Alpine from 'alpinejs';

Alpine.data('proposalForm', () => {
    let initialData = {
        initialItems: [],
        initialDoctor: '',
        initialNotes: '',
        hasOldInput: false,
        hasFlashSuccess: false,
    };
    const dataElement = document.getElementById('proposal-form-data');
    if (dataElement && dataElement.textContent) {
        try {
            initialData = Object.assign(initialData, JSON.parse(dataElement.textContent));
        } catch (e) {
            console.error('Failed to parse proposal initial data', e);
        }
    }

    const initialItems = initialData.initialItems && initialData.initialItems.length
        ? initialData.initialItems.map((it) => ({
            medicine_name: it.medicine_name || '',
            principal_name: it.principal_name || '',
            clinical_justification: it.clinical_justification || '',
            autoFilled: false,
        }))
        : [{ medicine_name: '', principal_name: '', clinical_justification: '', autoFilled: false }];

    return {
        doctorInput: initialData.initialDoctor || '',
        doctorSuggestions: [],
        doctorIsOpen: false,
        doctorLoading: false,
        doctorHighlightedIndex: -1,
        doctorDebounceTimer: null,
        doctorAbortController: null,
        items: initialItems,
        notes: initialData.initialNotes || '',
        submitting: false,
        isKeyboardVisible: false,

        // Draft Management State
        storageKey: 'formulary_draft_v1',
        isDraftRestored: false,
        autoSaveTimer: null,

        init() {
            // Priority 1: Submission success -> unconditionally purge draft
            if (initialData.hasFlashSuccess) {
                try {
                    localStorage.removeItem(this.storageKey);
                } catch (e) {
                    console.warn('LocalStorage access error on purge', e);
                }
            } else if (!initialData.hasOldInput) {
                // Priority 2 & 3: Clean fresh form -> restore draft from localStorage if available (skipped if old input exists)
                try {
                    const rawDraft = localStorage.getItem(this.storageKey);
                    if (rawDraft) {
                        const parsed = JSON.parse(rawDraft);
                        const hasDoctor = typeof parsed.doctorInput === 'string' && parsed.doctorInput.trim() !== '';
                        const hasNotes = typeof parsed.notes === 'string' && parsed.notes.trim() !== '';
                        const hasItems = Array.isArray(parsed.items) && parsed.items.some(
                            (it) => typeof it.medicine_name === 'string' && it.medicine_name.trim() !== ''
                        );

                        if (hasDoctor || hasNotes || hasItems) {
                            this.doctorInput = parsed.doctorInput || '';
                            this.notes = parsed.notes || '';
                            if (Array.isArray(parsed.items) && parsed.items.length > 0) {
                                this.items = parsed.items.map((it) => ({
                                    medicine_name: it.medicine_name || '',
                                    principal_name: it.principal_name || '',
                                    clinical_justification: it.clinical_justification || '',
                                    autoFilled: Boolean(it.autoFilled),
                                }));
                            }
                            this.isDraftRestored = true;
                        }
                    }
                } catch (e) {
                    console.warn('Failed to restore draft from localStorage', e);
                }
            }

            // Setup reactive deep watchers for auto-save
            this.$watch('doctorInput', () => this.scheduleAutoSave());
            this.$watch('notes', () => this.scheduleAutoSave());
            this.$watch('items', () => this.scheduleAutoSave(), { deep: true });

            // Tab Guard: Prompt user before leaving with unsaved changes
            window.addEventListener('beforeunload', (e) => {
                if (this.submitting) return;
                if (this.hasUnsavedChanges()) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            // Mobile Virtual Keyboard Detection via Visual Viewport
            if (window.visualViewport) {
                let baseHeight = window.visualViewport.height;
                const checkKeyboard = () => {
                    this.isKeyboardVisible = window.visualViewport.height < baseHeight - 120;
                };

                window.visualViewport.addEventListener('resize', checkKeyboard);
                window.addEventListener('orientationchange', () => {
                    setTimeout(() => {
                        baseHeight = window.visualViewport.height;
                        checkKeyboard();
                    }, 150);
                });
            }
        },

        isFormEmpty() {
            const noDoctor = !this.doctorInput || this.doctorInput.trim() === '';
            const noNotes = !this.notes || this.notes.trim() === '';
            const noItems = !this.items || this.items.length === 0 || this.items.every(
                (it) => (!it.medicine_name || it.medicine_name.trim() === '') &&
                        (!it.principal_name || it.principal_name.trim() === '') &&
                        (!it.clinical_justification || it.clinical_justification.trim() === '')
            );
            return noDoctor && noNotes && noItems;
        },

        scheduleAutoSave() {
            clearTimeout(this.autoSaveTimer);
            this.autoSaveTimer = setTimeout(() => {
                this.saveDraft();
            }, 500);
        },

        saveDraft() {
            if (this.submitting) return;

            try {
                if (this.isFormEmpty()) {
                    localStorage.removeItem(this.storageKey);
                    return;
                }

                const payload = {
                    doctorInput: this.doctorInput,
                    items: this.items,
                    notes: this.notes,
                    savedAt: Date.now(),
                };
                localStorage.setItem(this.storageKey, JSON.stringify(payload));
            } catch (e) {
                console.warn('Failed to save draft to localStorage', e);
            }
        },

        clearDraft() {
            try {
                localStorage.removeItem(this.storageKey);
            } catch (e) {
                console.warn('Failed to clear draft from localStorage', e);
            }

            this.doctorInput = '';
            this.notes = '';
            this.items = [{ medicine_name: '', principal_name: '', clinical_justification: '', autoFilled: false }];
            this.isDraftRestored = false;
        },

        handleDoctorInput(e) {
            clearTimeout(this.doctorDebounceTimer);
            if (this.doctorAbortController) {
                this.doctorAbortController.abort();
                this.doctorAbortController = null;
            }

            const val = e.target.value.trim();
            this.doctorHighlightedIndex = -1;

            if (val.length < 1) {
                this.doctorSuggestions = [];
                this.doctorIsOpen = false;
                this.doctorLoading = false;
                return;
            }

            this.doctorLoading = true;
            this.doctorIsOpen = true;

            this.doctorDebounceTimer = setTimeout(() => {
                if (this.doctorInput.trim().length < 1) {
                    this.doctorSuggestions = [];
                    this.doctorIsOpen = false;
                    this.doctorLoading = false;
                    return;
                }

                this.doctorAbortController = new AbortController();
                fetch(`/api/suggestions/doctors?q=${encodeURIComponent(val)}`, {
                    signal: this.doctorAbortController.signal,
                })
                    .then((res) => res.json())
                    .then((json) => {
                        if (this.doctorInput.trim().length < 1) {
                            this.doctorSuggestions = [];
                            this.doctorIsOpen = false;
                            this.doctorLoading = false;
                            return;
                        }
                        this.doctorSuggestions = json.data || [];
                        this.doctorLoading = false;
                        this.doctorIsOpen = true;
                    })
                    .catch((err) => {
                        if (err && err.name === 'AbortError') return;
                        this.doctorSuggestions = [];
                        this.doctorLoading = false;
                    });
            }, 200);
        },

        navigateDoctorDown() {
            if (!this.doctorIsOpen) return;
            const max = this.doctorSuggestions.length - 1;
            if (this.doctorHighlightedIndex < max) {
                this.doctorHighlightedIndex++;
            }
        },

        navigateDoctorUp() {
            if (!this.doctorIsOpen) return;
            if (this.doctorHighlightedIndex > 0) {
                this.doctorHighlightedIndex--;
            }
        },

        selectDoctorHighlighted() {
            if (
                this.doctorHighlightedIndex >= 0 &&
                this.doctorSuggestions[this.doctorHighlightedIndex]
            ) {
                this.selectDoctor(this.doctorSuggestions[this.doctorHighlightedIndex].name);
            } else if (this.doctorInput.trim() !== '') {
                this.doctorIsOpen = false;
            }
        },

        selectDoctor(name) {
            clearTimeout(this.doctorDebounceTimer);
            if (this.doctorAbortController) {
                this.doctorAbortController.abort();
                this.doctorAbortController = null;
            }
            this.doctorInput = name;
            this.doctorIsOpen = false;
            this.doctorLoading = false;
            this.doctorHighlightedIndex = -1;
            this.doctorSuggestions = [];
        },

        closeDoctorDropdown() {
            this.doctorIsOpen = false;
            this.doctorHighlightedIndex = -1;
        },

        hasUnsavedChanges() {
            if (this.submitting) return false;
            const hasDoctor = Boolean(this.doctorInput && this.doctorInput.trim() !== '');
            const hasNotes = Boolean(this.notes && this.notes.trim() !== '');
            const hasItems = Array.isArray(this.items) && this.items.some(
                (it) => Boolean(it.medicine_name?.trim() || it.principal_name?.trim() || it.clinical_justification?.trim())
            );
            return hasDoctor || hasNotes || hasItems;
        },

        submitForm() {
            if (this.submitting) return;
            const form = document.getElementById('proposal-form');
            if (!form) return;

            // Trigger standard HTML5 validation
            if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                return;
            }

            this.submitting = true;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        },

        addItem() {
            if (this.items.length < 50) {
                this.items.push({ medicine_name: '', principal_name: '', clinical_justification: '', autoFilled: false });
                this.$nextTick(() => {
                    const inputs = document.querySelectorAll('[data-medicine-input]');
                    if (inputs.length > 0) {
                        inputs[inputs.length - 1].focus();
                    }
                });
            }
        },

        removeItem(index) {
            if (this.items.length > 1) {
                this.items.splice(index, 1);
            }
        },
    };
});

Alpine.data('medicineAutocomplete', (item) => ({
    isOpen: false,
    suggestions: [],
    highlightedIndex: -1,
    debounceTimer: null,
    abortController: null,

    handleInput(e) {
        clearTimeout(this.debounceTimer);
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        const val = e.target.value.trim();
        this.highlightedIndex = -1;
        if (val.length < 1) {
            this.suggestions = [];
            this.isOpen = false;
            return;
        }

        this.debounceTimer = setTimeout(() => {
            if (item.medicine_name.trim().length < 1) {
                this.suggestions = [];
                this.isOpen = false;
                return;
            }

            this.abortController = new AbortController();
            fetch(`/api/suggestions/medicines?q=${encodeURIComponent(val)}`, {
                signal: this.abortController.signal,
            })
                .then((res) => res.json())
                .then((json) => {
                    if (item.medicine_name.trim().length < 1) {
                        this.suggestions = [];
                        this.isOpen = false;
                        return;
                    }
                    this.suggestions = json.data || [];
                    this.isOpen = this.suggestions.length > 0;
                })
                .catch((err) => {
                    if (err && err.name === 'AbortError') return;
                    this.suggestions = [];
                    this.isOpen = false;
                });
        }, 180);
    },

    navigateDown() {
        if (!this.isOpen) return;
        if (this.highlightedIndex < this.suggestions.length - 1) {
            this.highlightedIndex++;
        }
    },

    navigateUp() {
        if (!this.isOpen) return;
        if (this.highlightedIndex > 0) {
            this.highlightedIndex--;
        }
    },

    selectHighlighted() {
        if (this.highlightedIndex >= 0 && this.suggestions[this.highlightedIndex]) {
            this.pick(this.suggestions[this.highlightedIndex]);
        }
    },

    pick(sug) {
        clearTimeout(this.debounceTimer);
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
        item.medicine_name = sug.medicine_name;
        if (sug.principal_name && (!item.principal_name || item.autoFilled)) {
            item.principal_name = sug.principal_name;
            item.autoFilled = true;
        }
        this.suggestions = [];
        this.isOpen = false;
    },
}));

Alpine.data('principalAutocomplete', (item) => ({
    isOpen: false,
    suggestions: [],
    highlightedIndex: -1,
    debounceTimer: null,
    abortController: null,

    handleInput(e) {
        clearTimeout(this.debounceTimer);
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }

        item.autoFilled = false;
        const val = e.target.value.trim();
        this.highlightedIndex = -1;
        if (val.length < 1) {
            this.suggestions = [];
            this.isOpen = false;
            return;
        }

        this.debounceTimer = setTimeout(() => {
            if ((item.principal_name || '').trim().length < 1) {
                this.suggestions = [];
                this.isOpen = false;
                return;
            }

            this.abortController = new AbortController();
            fetch(`/api/suggestions/principals?q=${encodeURIComponent(val)}`, {
                signal: this.abortController.signal,
            })
                .then((res) => res.json())
                .then((json) => {
                    if ((item.principal_name || '').trim().length < 1) {
                        this.suggestions = [];
                        this.isOpen = false;
                        return;
                    }
                    this.suggestions = json.data || [];
                    this.isOpen = this.suggestions.length > 0;
                })
                .catch((err) => {
                    if (err && err.name === 'AbortError') return;
                    this.suggestions = [];
                    this.isOpen = false;
                });
        }, 180);
    },

    navigateDown() {
        if (!this.isOpen) return;
        if (this.highlightedIndex < this.suggestions.length - 1) {
            this.highlightedIndex++;
        }
    },

    navigateUp() {
        if (!this.isOpen) return;
        if (this.highlightedIndex > 0) {
            this.highlightedIndex--;
        }
    },

    selectHighlighted() {
        if (this.highlightedIndex >= 0 && this.suggestions[this.highlightedIndex]) {
            this.pick(this.suggestions[this.highlightedIndex]);
        }
    },

    pick(name) {
        clearTimeout(this.debounceTimer);
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
        item.principal_name = name;
        item.autoFilled = false;
        this.suggestions = [];
        this.isOpen = false;
    },
}));

Alpine.data('customSelect', (config) => ({
    isOpen: false,
    value: config.value || '',
    options: config.options || [],
    search: '',
    highlightedIndex: -1,

    init() {
        this.$watch('isOpen', (val) => {
            if (val) {
                this.search = '';
                this.highlightedIndex = -1;
                this.$nextTick(() => {
                    if (this.$refs.searchInput) {
                        this.$refs.searchInput.focus();
                    }
                });
            }
        });
    },

    get filteredOptions() {
        const q = this.search.trim().toLowerCase();
        if (!q) return this.options;
        return this.options.filter((opt) => {
            const label = typeof opt === 'string' ? opt : (opt.label || opt.value || '');
            return label.toLowerCase().includes(q);
        });
    },

    get selectedLabel() {
        if (!this.value) return config.placeholder || 'Pilih...';
        const found = this.options.find((opt) => {
            const val = typeof opt === 'string' ? opt : opt.value;
            return val === this.value;
        });
        if (!found) return this.value;
        return typeof found === 'string' ? found : found.label;
    },

    select(val) {
        this.value = val;
        this.isOpen = false;
        this.search = '';
        this.highlightedIndex = -1;
    },

    clear() {
        this.value = '';
        this.isOpen = false;
        this.search = '';
        this.highlightedIndex = -1;
    },

    navigateDown() {
        const list = this.filteredOptions;
        if (this.highlightedIndex < list.length - 1) {
            this.highlightedIndex++;
            this.scrollToHighlighted();
        }
    },

    navigateUp() {
        if (this.highlightedIndex > 0) {
            this.highlightedIndex--;
            this.scrollToHighlighted();
        }
    },

    selectHighlighted() {
        const list = this.filteredOptions;
        if (this.highlightedIndex >= 0 && list[this.highlightedIndex]) {
            const item = list[this.highlightedIndex];
            this.select(typeof item === 'string' ? item : item.value);
        }
    },

    scrollToHighlighted() {
        this.$nextTick(() => {
            const listEl = this.$refs.optionsList;
            if (!listEl) return;
            const highlightedEl = listEl.querySelector(`[data-index="${this.highlightedIndex}"]`);
            if (highlightedEl) {
                highlightedEl.scrollIntoView({ block: 'nearest' });
            }
        });
    },
}));

window.Alpine = Alpine;

Alpine.start();
