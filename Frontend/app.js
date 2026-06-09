const menuButton = document.querySelector("[data-menu-toggle]");
const nav = document.querySelector(".nav");
const loginForm = document.querySelector("[data-login-form]");
const loginPanel = document.querySelector("[data-login-panel]");
const memberDashboard = document.querySelector("[data-member-dashboard]");
const logoutButton = document.querySelector("[data-logout]");
const enrolForm = document.querySelector("[data-enrol-form]");
const formStatus = document.querySelector("[data-form-status]");

menuButton?.addEventListener("click", () => {
  nav.classList.toggle("open");
});

nav?.addEventListener("click", () => {
  nav.classList.remove("open");
});

loginForm?.addEventListener("submit", (event) => {
  event.preventDefault();
  loginPanel.classList.add("hidden");
  memberDashboard.classList.remove("hidden");
  memberDashboard.scrollIntoView({ behavior: "smooth", block: "start" });
});

logoutButton?.addEventListener("click", () => {
  memberDashboard.classList.add("hidden");
  loginPanel.classList.remove("hidden");
  loginPanel.scrollIntoView({ behavior: "smooth", block: "start" });
});

enrolForm?.addEventListener("submit", (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(enrolForm).entries());
  formStatus.textContent = `Application received for ${data.name}. The VP Membership can contact ${data.email}.`;
  enrolForm.reset();
});
