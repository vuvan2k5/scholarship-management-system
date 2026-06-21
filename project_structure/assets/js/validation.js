// ======================================================
// validation.js
// Global Frontend Validation
// ======================================================

// AUTO CLOSE ALERTS

setTimeout(() => {

    const alerts = document.querySelectorAll('.alert');

    alerts.forEach(alert => {

        const bsAlert = new bootstrap.Alert(alert);

        bsAlert.close();

    });

}, 4000);

// CONFIRM DELETE

document
    .querySelectorAll('.btn-delete')
    .forEach(button => {

        button.addEventListener('click', function (e) {

            const confirmed = confirm(
                'Are you sure you want to delete this item?'
            );

            if (!confirmed) {

                e.preventDefault();

            }

        });

    });

// SIMPLE REQUIRED VALIDATION

document
    .querySelectorAll('form')
    .forEach(form => {

        form.addEventListener('submit', function (e) {

            let valid = true;

            form
                .querySelectorAll('[required]')
                .forEach(input => {

                    if (!input.value.trim()) {

                        valid = false;

                        input.classList.add('is-invalid');

                    } else {

                        input.classList.remove('is-invalid');

                    }

                });

            if (!valid) {

                e.preventDefault();

                alert('Please fill in all required fields.');

            }

        });

    });