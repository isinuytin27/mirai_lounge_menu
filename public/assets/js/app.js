window.viewport = document.getElementById("viewport");

document.addEventListener("DOMContentLoaded", () => {
  console.log("Mirai Lounge UI started");

  const home = document.querySelector(".screen.home");
  if (!home || !window.navigation?.goTo) return;

  home.querySelectorAll(".nav-hints [data-nav-x][data-nav-y]").forEach((el) => {
    el.addEventListener("click", () => {
      const x = parseInt(el.getAttribute("data-nav-x"), 10);
      const y = parseInt(el.getAttribute("data-nav-y"), 10);
      if (Number.isNaN(x) || Number.isNaN(y)) return;
      navigation.goTo(x, y);
    });
  });

  document
    .querySelectorAll("[data-gallery-go-home], [data-booking-home], [data-about-home], [data-menu-go-home]")
    .forEach((el) => {
      el.addEventListener("click", () => navigation.goTo(1, 1));
    });
});
