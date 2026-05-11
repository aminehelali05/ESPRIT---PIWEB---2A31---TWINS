/**
 * Event & Resource validation script
 * 
 * Strictly handles validation via JavaScript before submission.
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log("Validation script loaded and active.");
    
    // --- Helpers ---
    const showAlert = (message) => {
        console.warn("Validation failed:", message);
        // Check if SweetAlert2 is available (from dashboard/admin)
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Validation Error',
                text: message,
                confirmButtonColor: '#38bdf8'
            });
        } else {
            alert(message);
        }
    };

    const validateEventForm = (form) => {
        const titleField = form.querySelector('[name="title"]');
        const dateField = form.querySelector('[name="event_date"]');
        const locationField = form.querySelector('[name="location"]');
        const descField = form.querySelector('[name="description"]');

        if (!titleField || !dateField || !locationField || !descField) {
            console.error("Missing form fields during validation.");
            return true; // Let it submit if script is broken
        }

        const title = titleField.value.trim();
        const date = dateField.value;
        const location = locationField.value.trim();
        const description = descField.value.trim();

        if (title === "") {
            showAlert("Please enter a title for the event.");
            return false;
        }

        if (title.length < 5) {
            showAlert("The event title must be at least 5 characters long.");
            return false;
        }

        if (date === "") {
            showAlert("Please select a date and time for the event.");
            return false;
        }

        const selectedDate = new Date(date);
        const now = new Date();
        if (selectedDate < now) {
            showAlert("The event date cannot be in the past.");
            return false;
        }

        if (location === "") {
            showAlert("The event location cannot be empty.");
            return false;
        }

        if (description === "") {
            showAlert("Please enter a description.");
            return false;
        }

        if (description.length < 20) {
            showAlert("The description should be at least 20 characters long.");
            return false;
        }

        console.log("Event validation successful, submitting...");
        return true;
    };

    const validateResourceForm = (form) => {
        const titleField = form.querySelector('[name="title"]');
        const descField = form.querySelector('[name="description"]');

        if (!titleField || !descField) return true;

        const title = titleField.value.trim();
        const description = descField.value.trim();

        if (title === "" || title.length < 3) {
            showAlert("The resource title must be at least 3 characters long.");
            return false;
        }

        if (description === "" || description.length < 10) {
            showAlert("Please provide a more detailed description (min 10 characters).");
            return false;
        }

        console.log("Resource validation successful, submitting...");
        return true;
    };

    // --- Event Listeners ---

    const createForm = document.getElementById('eventCreateForm');
    if (createForm) {
        console.log("Attached listener to eventCreateForm");
        createForm.addEventListener('submit', (e) => {
            if (!validateEventForm(createForm)) {
                e.preventDefault();
            }
        });
    }

    const editForm = document.getElementById('eventEditForm');
    if (editForm) {
        console.log("Attached listener to eventEditForm");
        editForm.addEventListener('submit', (e) => {
            if (!validateEventForm(editForm)) {
                e.preventDefault();
            }
        });
    }

    const resourceForm = document.getElementById('resourceAdminForm');
    if (resourceForm) {
        console.log("Attached listener to resourceAdminForm");
        resourceForm.addEventListener('submit', (e) => {
            if (!validateResourceForm(resourceForm)) {
                e.preventDefault();
            }
        });
    }
});
