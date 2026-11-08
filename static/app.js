function toast(message) {
	const element = document.createElement("div");
	element.className = "toast";
	element.textContent = message;
	document.getElementById("toasts").append(element);
	setTimeout(() => element.remove(), 3000);
}

function removePendingItems() {
	document.querySelectorAll("tr.pending").forEach((row) => row.remove());
}

// The redirected page replaces the pending row; "another" waits for it so the dialog reopens with fresh options.
document.addEventListener("submit", (event) => {
	const form = event.target;
	if (!form.matches("[data-optimistic-item]") || event.submitter?.name === "another") {
		return;
	}
	const template = document.getElementById("pending-item");
	const option = form.elements.product_id.selectedOptions[0];
	if (!template || !option?.value) {
		return;
	}

	const fields = { ...option.dataset, quantity: form.elements.quantity.value };
	fields.meta = `${fields.category} · ${fields.size} ${fields.size_type}`;
	const row = template.content.firstElementChild.cloneNode(true);
	row.querySelectorAll("[data-field]").forEach((element) => {
		element.textContent = fields[element.dataset.field];
	});

	const tbody = template.parentElement.querySelector("tbody");
	tbody.querySelector("td.empty")?.parentElement.remove();
	tbody.append(row);
	form.closest("dialog").close();
});

document.addEventListener("htmx:sendError", () => {
	removePendingItems();
	toast("You're offline, changes not saved");
});
document.addEventListener("htmx:responseError", () => {
	removePendingItems();
	toast("Something went wrong");
});

if ("serviceWorker" in navigator) {
	navigator.serviceWorker.register("/sw.js");
}
