

import Alpine from 'alpinejs';
import { latestRequest } from './loading/latest-request';
import { initPageLoading } from './loading/page-loading';
import { initPwa } from './pwa/install';

const gsmBasicCharacters = new Set(Array.from(
    " @£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ!\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà",
));
const gsmExtendedCharacters = new Set(Array.from('^{}\\[~]|€\f'));
const estimateSms = (message) => {
    const characters = Array.from(message);
    const isGsm = characters.every((character) =>
        gsmBasicCharacters.has(character) || gsmExtendedCharacters.has(character),
    );
    const units = isGsm
        ? characters.reduce((total, character) => total + (gsmExtendedCharacters.has(character) ? 2 : 1), 0)
        : characters.length;
    const singleSegmentLimit = isGsm ? 160 : 70;
    const multipartSegmentLimit = isGsm ? 153 : 67;

    return {
        characters: characters.length,
        encoding: isGsm ? 'GSM-7' : 'Unicode',
        segments: characters.length === 0
            ? 0
            : units <= singleSegmentLimit
                ? 1
                : Math.ceil(units / multipartSegmentLimit),
    };
};

window.Alpine = Alpine;
window.kkktLatestRequest = latestRequest;
initPageLoading();
initPwa();

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

    get characterCount() {
        return estimateSms(this.message).characters;
    },

    get encodingType() {
        return estimateSms(this.message).encoding;
    },

    get segmentsPerRecipient() {
        return estimateSms(this.message).segments;
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

Alpine.data('smsManualComposer', (confirmMessage) => ({
    message: '',
    submitting: false,

    get characterCount() {
        return estimateSms(this.message).characters;
    },

    get encodingType() {
        return estimateSms(this.message).encoding;
    },

    get segments() {
        return estimateSms(this.message).segments;
    },

    confirmSubmit(event) {
        if (!this.message.trim() || !window.confirm(confirmMessage)) {
            event.preventDefault();
            return;
        }

        this.submitting = true;
    },
}));

Alpine.start();
