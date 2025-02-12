// preventing back navigtion in login form
function preventBack() {
    window.history.forward();
}
setTimeout(preventBack, 0);
window.onunload = function() { null };

// toggle sidebar
function toggleSidebar() {
    let sidebar = document.getElementById("sidebar");
    sidebar.classList.toggle("show-sidebar");
}

// Close sidebar when clicking outside (for better UX)
document.addEventListener("click", function (event) {
    let sidebar = document.getElementById("sidebar");
    let toggleBtn = document.querySelector(".toggle-btn");

    if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
        sidebar.classList.remove("show-sidebar");
    }
});

// dropdown event
function toggleDropdown(event) {
    event.preventDefault();
    let dropdown = event.currentTarget.parentElement;
    dropdown.classList.toggle("active");
}

// prevent back navigation in dashbaord
window.history.pushState(null, "", window.location.href);
        window.addEventListener("popstate", function() {
        window.history.pushState(null, "", window.location.href);
});


// date of birth
document.addEventListener("DOMContentLoaded", function () {
    const dobInput = document.getElementById("dob");

    if (!dobInput) {
        console.error("Date of Birth input not found! Check the ID in your HTML.");
        return; // Stop execution if element is missing
    }

    const currentYear = new Date().getFullYear(); // Get the current year
    const minYear = currentYear - 3; // Calculate the minimum year allowed
    const maxDob = `${minYear}-01-01`; // Set max date to Jan 1st, 3 years ago

    dobInput.setAttribute("max", maxDob); // Apply restriction
});


// make payments handling
function updateFormAction() {
    let feeType = document.getElementById("fee_type").value;
    let form = document.getElementById("paymentForm");

    if (feeType === "lunch_fees") {
        form.action = "lunch-fee.php";
    } else if (feeType === "school_fees") {
        form.action = "school-fee-payment.php";
    } else if (feeType === "admission" || feeType === "Activity" || feeType === "Exam" || feeType === "Interview") {
        form.action = "others.php"; // Redirects correctly
    } else {
        form.action = "make-payments.php"; // Optional fallback
    }
}

// Set initial form action
updateFormAction();
// show pasword
document.addEventListener("DOMContentLoaded", function () {
    const togglePassword = document.getElementById("togglePassword");
    const passwordInput = document.getElementById("password");

    if (togglePassword) {
        togglePassword.addEventListener("click", function () {
            if (passwordInput.type === "password") {
                passwordInput.type = "text"; // Show password
                this.textContent = "🙈"; // Change icon
            } else {
                passwordInput.type = "password"; // Hide password
                this.textContent = "👁️"; // Change icon
            }
        });
    }
});

// purchase uniform
function updateTotalPrice() {
    var total = 0;
    document.querySelectorAll(".uniform-item").forEach(function(row) {
        var checkbox = row.querySelector("input[type='checkbox']");
        var quantityInput = row.querySelector(".quantity");
        var price = parseFloat(quantityInput.dataset.price) || 0;
        var quantity = parseInt(quantityInput.value) || 0;

        // Only add price if the checkbox is checked and quantity is greater than 0
        if (checkbox.checked && quantity > 0) {
            total += price * quantity;
        }
    });
    document.getElementById("total_price").textContent = "Total Price: KES " + total.toFixed(2);
}

// Attach event listeners to checkboxes and quantity inputs
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".uniform-item").forEach(function(row) {
        var checkbox = row.querySelector("input[type='checkbox']");
        var quantityInput = row.querySelector(".quantity");

        // Update total price when checkbox is clicked
        checkbox.addEventListener("change", function() {
            if (!this.checked) {
                quantityInput.value = 0; // Reset quantity if unchecked
            }
            updateTotalPrice();
        });
        // Update total price when quantity is changed
        quantityInput.addEventListener("input", updateTotalPrice);
    });
});    

//
document.addEventListener("DOMContentLoaded", function () {
    function updateTotalPrice() {
        let total = 0;

        document.querySelectorAll('.book-checkbox:checked').forEach(function (checkbox) {
            let bookItem = checkbox.closest('.book-item');
            let quantityInput = bookItem.querySelector('.quantity');
            let price = parseFloat(quantityInput.getAttribute('data-price')) || 0;
            let quantity = parseInt(quantityInput.value) || 1;

            total += price * quantity; // Ensure price is multiplied by quantity
        });

        document.getElementById("total_price").textContent = "Total Price: KES " + total.toFixed(2);
    }

    // Attach event listeners to checkboxes and quantity inputs
    document.querySelectorAll('.book-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', updateTotalPrice);
    });

    document.querySelectorAll('.quantity').forEach(function (input) {
        input.addEventListener('input', updateTotalPrice);
    });

    // Run the function once on page load
    updateTotalPrice();
});