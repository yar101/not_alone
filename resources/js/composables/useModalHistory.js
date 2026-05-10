import { watch, onUnmounted } from 'vue';

// Global stack to keep track of active modals (LIFO)
const modalStack = [];

/**
 * Counter to ignore popstate events triggered by our own history.back() calls.
 * This prevents the global listener from incorrectly unwinding the stack
 * when returning to a state that was corrupted (e.g. by Inertia's router.reload).
 */
let ignoreNextPopCount = 0;

// Single global listener for browser back navigation
if (typeof window !== 'undefined') {
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
    // Unique ID for this modal instance
    const id = Math.random().toString(36).substring(2, 11);

    const modalContext = {
        id,
        name,
        isPushed: false,
        setOpen: (val) => { isOpen.value = val; }
    };

    const pushState = () => {
        if (!modalContext.isPushed) {
            // Push state with unique ID
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
            
            // Increment ignore counter so the upcoming popstate event
            // doesn't trigger our global listener.
            ignoreNextPopCount++;
            
            // Go back in history to remove the pushed state.
            history.back();
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
