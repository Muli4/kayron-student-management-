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
    } else {
        form.action = "school-fee-payment.php";
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
        var quantity = row.querySelector(".quantity").value;
        var price = row.querySelector(".quantity").dataset.price;
        if (quantity > 0) {
            total += price * quantity;
        }
    });
    document.getElementById("total_price").textContent = "Total Price: KES " + total.toFixed(2);
}