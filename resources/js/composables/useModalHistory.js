import { watch, onUnmounted } from 'vue';

// Global stack to keep track of active modals (LIFO)
const modalStack = [];

/**
 * Counter to ignore popstate events triggered by our own history.back() calls.
 */
let ignoreNextPopCount = 0;

/**
 * Robust popstate listener.
 */
if (typeof window !== 'undefined') {
    // ── Patch history.replaceState ──────────────────────────────────────────
    // This is crucial. Inertia.js (and other SPA routers) frequently 
    // call replaceState, which wipes our custom metadata from history.state.
    // We intercept these calls and merge our data back in.
    if (!window.__modalHistoryPatched) {
        const originalReplaceState = history.replaceState;
        history.replaceState = function (state, title, url) {
            const currentState = history.state;
            if (currentState?.__modalId && state && typeof state === 'object' && !state.__modalId) {
                Object.assign(state, {
                    __modalId: currentState.__modalId,
                    modal: currentState.modal
                });
            }
            return originalReplaceState.apply(this, [state, title, url]);
        };
        window.__modalHistoryPatched = true;
    }

    window.addEventListener('popstate', (event) => {
        // If this navigation was triggered by our own manual close (history.back),
        // we just decrement the counter and skip our logic.
        if (ignoreNextPopCount > 0) {
            ignoreNextPopCount--;
            return;
        }

        const currentStateId = event.state?.__modalId;
        
        // Unwind the stack until we find the modal matching the current state
        while (modalStack.length > 0) {
            const topModal = modalStack[modalStack.length - 1];
            
            // If the top modal matches the state we just arrived at, stop.
            if (topModal.id === currentStateId) {
                break;
            }
            
            // Otherwise, this modal is no longer in the current history branch, so close it.
            const modal = modalStack.pop();
            modal.isPushed = false;
            if (modal.setOpen) {
                modal.setOpen(false);
            }
        }
    });
}

/**
 * Composable to manage browser history for modals.
 * Allows closing modals using the browser's "Back" button/gesture.
 * Supports nested/stacked modals correctly.
 * 
 * @param {import('vue').Ref<boolean>} isOpen - Ref to the modal's open state.
 * @param {string} [name='modal'] - Optional name for the history state.
 */
export function useModalHistory(isOpen, name = 'modal') {
    // Check if we are on mobile. History management is usually 
    // only expected/safe on mobile devices for UI overlays.
    const isMobile = () => typeof window !== 'undefined' && window.innerWidth <= 768;

    // Unique ID for this modal instance
    const id = Math.random().toString(36).substring(2, 11);

    const modalContext = {
        id,
        name,
        isPushed: false,
        setOpen: (val) => { isOpen.value = val; }
    };

    const pushState = () => {
        // ONLY push state on mobile. Desktop users prefer the back button 
        // to go to the previous page, not close an overlay.
        if (!modalContext.isPushed && isMobile()) {
            history.pushState({ modal: name, __modalId: id }, '');
            modalContext.isPushed = true;
            modalStack.push(modalContext);
        }
    };

    const popState = () => {
        if (modalContext.isPushed) {
            modalContext.isPushed = false;
            
            // Remove from stack if it's still there
            const index = modalStack.indexOf(modalContext);
            if (index !== -1) {
                modalStack.splice(index, 1);
            }
            
            // Only call history.back() if we are actually at the state we pushed.
            // This prevents background navigation if Inertia or another script
            // already replaced the state or navigated away.
            if (history.state?.__modalId === id) {
                ignoreNextPopCount++;
                history.back();
            }
        }
    };

    // Watch the component's state
    watch(isOpen, (val) => {
        if (val) {
            pushState();
        } else {
            popState();
        }
    });

    onUnmounted(() => {
        if (modalContext.isPushed) {
            popState();
        }
    });
}
