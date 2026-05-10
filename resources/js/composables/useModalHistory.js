import { watch, onUnmounted } from 'vue';

// Global stack to keep track of active modals (LIFO)
const modalStack = [];

/**
 * Robust popstate listener.
 */
if (typeof window !== 'undefined') {
    // ── Patch history.replaceState ──────────────────────────────────────────
    // Intercept calls to replaceState and merge our metadata back in.
    if (!window.__modalHistoryPatched) {
        const originalReplaceState = history.replaceState;
        history.replaceState = function (state, title, url) {
            const currentState = history.state;
            if (currentState?.__modalId) {
                // Ensure state is a patchable object
                let newState = state;
                if (!newState || typeof newState !== 'object') {
                    newState = {};
                }
                // Only merge if not already present in the new state
                if (!newState.__modalId) {
                    newState.__modalId = currentState.__modalId;
                    newState.modal = currentState.modal;
                }
                return originalReplaceState.apply(this, [newState, title, url]);
            }
            return originalReplaceState.apply(this, [state, title, url]);
        };
        window.__modalHistoryPatched = true;
    }

    window.addEventListener('popstate', (event) => {
        const currentStateId = event.state?.__modalId;
        const currentPath = window.location.pathname;
        
        // Unwind the stack until we find the modal matching the current state
        while (modalStack.length > 0) {
            const topModal = modalStack[modalStack.length - 1];
            
            // If the top modal matches the state ID we just arrived at, we found our place.
            if (topModal.id === currentStateId) {
                break;
            }
            
            // ── The Path-Aware Safety Net ───────────────────────────────────
            // If the ID is missing (Inertia reload wiped it) but we are still 
            // on the same page, DO NOT close the modals. The user didn't 
            // actually navigate back; it was just a data reload.
            if (!currentStateId && currentPath === topModal.path) {
                break;
            }
            
            // Otherwise, we are truly navigating back away from this modal.
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
 * 
 * @param {import('vue').Ref<boolean>} isOpen - Ref to the modal's open state.
 * @param {string} [name='modal'] - Optional name for the history state.
 */
export function useModalHistory(isOpen, name = 'modal') {
    const isMobile = () => typeof window !== 'undefined' && window.innerWidth <= 768;

    const id = Math.random().toString(36).substring(2, 11);
    const modalContext = {
        id,
        name,
        path: typeof window !== 'undefined' ? window.location.pathname : '',
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
            
            // Remove from stack immediately
            const index = modalStack.indexOf(modalContext);
            if (index !== -1) {
                modalStack.splice(index, 1);
            }
            
            // Only call history.back() if we are actually at the browser state that matches this modal.
            // This prevents "overshooting" and navigating the background page.
            if (history.state?.__modalId === id) {
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
