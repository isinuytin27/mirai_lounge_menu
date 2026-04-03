(function () {
  const about = document.querySelector(".screen.about");
  if (!about) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        about.classList.toggle("is-visible", e.isIntersecting);
      });
    },
    { threshold: 0.3, root: null }
  );

  observer.observe(about);
})();
