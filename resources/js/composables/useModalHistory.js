import { watch, onUnmounted } from 'vue';

// Global stack to keep track of active modals (LIFO)
const modalStack = [];

// Single global listener for browser back navigation
if (typeof window !== 'undefined') {
    window.addEventListener('popstate', (event) => {
        if (modalStack.length > 0) {
            // Pop the top modal
            const topModal = modalStack.pop();
            
            // Mark it as not pushed so its watcher doesn't trigger history.back()
            topModal.isPushed = false;
            
            // Update the component's state to close it
            if (topModal.setOpen) {
                topModal.setOpen(false);
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
    const modalContext = {
        name,
        isPushed: false,
        setOpen: (val) => { isOpen.value = val; }
    };

    const pushState = () => {
        if (!modalContext.isPushed) {
            history.pushState({ modal: name }, '');
            modalContext.isPushed = true;
            modalStack.push(modalContext);
        }
    };

    const popState = () => {
        if (modalContext.isPushed) {
            modalContext.isPushed = false;
            
            // Remove from stack
            const index = modalStack.indexOf(modalContext);
            if (index !== -1) {
                modalStack.splice(index, 1);
            }
            
            // Go back in history to remove the pushed state
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
