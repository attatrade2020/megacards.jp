(() => {
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const revealTargets = document.querySelectorAll(
    ".release-track__inner > div, .value-strip__grid > div, .ranking__heading, .ranking-card, .catalog__heading, .product-card, .shipping__visual, .shipping__details"
  );

  revealTargets.forEach((element, index) => {
    element.classList.add("reveal-on-scroll");
    element.style.transitionDelay = `${Math.min(index % 4, 3) * 70}ms`;
  });

  if (reduceMotion || !("IntersectionObserver" in window)) {
    revealTargets.forEach((element) => element.classList.add("is-visible"));
  } else {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.1, rootMargin: "0px 0px -30px" });
    revealTargets.forEach((element) => observer.observe(element));
  }

  const heroVisual = document.querySelector(".hero__visual");
  if (!reduceMotion && heroVisual) {
    heroVisual.addEventListener("pointermove", (event) => {
      const bounds = heroVisual.getBoundingClientRect();
      const x = (event.clientX - bounds.left) / bounds.width - 0.5;
      const y = (event.clientY - bounds.top) / bounds.height - 0.5;
      heroVisual.style.setProperty("--pointer-x", `${x * 10}px`);
      heroVisual.style.setProperty("--pointer-y", `${y * 8}px`);
    });
    heroVisual.addEventListener("pointerleave", () => {
      heroVisual.style.removeProperty("--pointer-x");
      heroVisual.style.removeProperty("--pointer-y");
    });
  }
})();
