/**
 * Tracks the most recent asynchronous lookup of one component.
 * Older requests are aborted and can never clear the pending state or overwrite the result of a newer one.
 */
export function latestRequest() {
    let controller = null;
    let sequence = 0;

    return {
        pending: false,

        /**
         * @param {(signal: AbortSignal) => Promise<any>} task
         * @returns {Promise<{stale: boolean, value?: any}>} stale results must be ignored by the caller
         */
        async run(task) {
            controller?.abort();
            const current = new AbortController();
            controller = current;
            const id = ++sequence;
            this.pending = true;

            try {
                const value = await task(current.signal);

                return id === sequence ? { stale: false, value } : { stale: true };
            } catch (error) {
                if (id !== sequence || current.signal.aborted) {
                    return { stale: true };
                }

                throw error;
            } finally {
                if (id === sequence) {
                    this.pending = false;
                    controller = null;
                }
            }
        },

        cancel() {
            controller?.abort();
            controller = null;
            sequence++;
            this.pending = false;
        },
    };
}
