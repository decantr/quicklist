function toast(message) {
	const element = document.createElement("div");
	element.className = "toast";
	element.textContent = message;
	document.getElementById("toasts").append(element);
	setTimeout(() => element.remove(), 3000);
}

document.addEventListener("htmx:sendError", () => toast("You're offline, changes not saved"));
document.addEventListener("htmx:responseError", () => toast("Something went wrong"));

if ("serviceWorker" in navigator) {
	navigator.serviceWorker.register("/sw.js");
}
