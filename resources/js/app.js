

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('smsCampaignSelector', (validPhoneIds, matchingCount, confirmMessage) => ({
    selectedIds: [],
    validPhoneIds: validPhoneIds.map(String),
    matchingCount,
    confirmMessage,
    mode: 'selected',
    message: '',
    submitting: false,

    init() {
        try {
            const storedIds = JSON.parse(sessionStorage.getItem('kkkt-sms-campaign-selected') || '[]');
            this.selectedIds = Array.isArray(storedIds) ? [...new Set(storedIds.map(String))] : [];
        } catch {
            this.selectedIds = [];
        }
    },

    persist() {
        sessionStorage.setItem('kkkt-sms-campaign-selected', JSON.stringify(this.selectedIds));
    },

    toggleMember(memberId, selected) {
        const id = String(memberId);
        this.selectedIds = selected
            ? [...new Set([...this.selectedIds, id])]
            : this.selectedIds.filter((selectedId) => selectedId !== id);
        this.persist();
    },

    selectPage(memberIds) {
        this.selectedIds = [...new Set([...this.selectedIds, ...memberIds.map(String)])];
        this.persist();
    },

    clearSelection() {
        this.selectedIds = [];
        this.persist();
    },

    get recipientCount() {
        return this.mode === 'filters' ? this.matchingCount : this.selectedIds.length;
    },

    get segmentsPerRecipient() {
        if (this.message === '') {
            return 0;
        }

        const characters = Array.from(this.message);
        const unicode = /[^\u0000-\u007f£¥èéùìòÇØøÅåΔΦΓΛΩΠΨΣΘΞÆæßÉÄÖÑÜ§¿äöñüà]/u.test(this.message);

        if (unicode) {
            return characters.length <= 70 ? 1 : Math.ceil(characters.length / 67);
        }

        const gsmUnits = characters.reduce((total, character) =>
            total + ('^{}\\[~]|€'.includes(character) ? 2 : 1), 0);

        return gsmUnits <= 160 ? 1 : Math.ceil(gsmUnits / 153);
    },

    confirmSubmit(event) {
        if (this.recipientCount < 1 || (this.mode === 'selected' && this.selectedIds.length < 1)) {
            event.preventDefault();
            return;
        }

        if (!window.confirm(this.confirmMessage.replace(':count', this.recipientCount))) {
            event.preventDefault();
            return;
        }

        this.submitting = true;
        sessionStorage.removeItem('kkkt-sms-campaign-selected');
    },
}));

Alpine.start();
