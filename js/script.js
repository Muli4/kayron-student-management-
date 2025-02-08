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
