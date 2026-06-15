const menuButton = document.querySelector("[data-menu-toggle]");
const nav        = document.querySelector(".nav");
const enrolForm  = document.querySelector("[data-tmc-enrol-form]");
const formStatus = document.querySelector("[data-tmc-form-status]");

menuButton?.addEventListener("click", () => {
  nav.classList.toggle("open");
});

nav?.addEventListener("click", (e) => {
  if (e.target.tagName === "A") nav.classList.remove("open");
});

enrolForm?.addEventListener("submit", (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(enrolForm).entries());
  if (formStatus) {
    formStatus.textContent = `Application received for ${data.name}. Our VP Membership will contact you at ${data.email}.`;
    formStatus.style.color = "#0f766e";
  }
  enrolForm.reset();
});
