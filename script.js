// LUNELLE WATER - script.js

// ---- ON PAGE LOAD ----
document.addEventListener("DOMContentLoaded", function() {

  // LOGO CLICK
  var logo = document.querySelector(".logo");
  if (logo) {
    logo.addEventListener("click", function() {
      if (window.location.pathname.indexOf("about") !== -1) {
        window.location.href = "index.html";
      } else if (window.location.pathname.indexOf("contact") !== -1) {
        window.location.href = "index.html";
      } else {
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });
  }

  // STICKY NAV
  var nav = document.querySelector("nav");
  if (nav) {
    window.addEventListener("scroll", function() {
      if (window.scrollY > 50) {
        nav.classList.add("scrolled");
      } else {
        nav.classList.remove("scrolled");
      }
    });
  }

  // SCROLL REVEAL
  var items = document.querySelectorAll(
    ".item-a, .item-b, .item-c, .item-d, .about-card, .contact-card"
  );

  if (items.length > 0 && "IntersectionObserver" in window) {
    var observer = new IntersectionObserver(function(entries) {
      for (var j = 0; j < entries.length; j++) {
        if (entries[j].isIntersecting) {
          entries[j].target.classList.add("revealed");
          observer.unobserve(entries[j].target);
        }
      }
    }, { threshold: 0.15 });

    for (var k = 0; k < items.length; k++) {
      items[k].classList.add("reveal-hidden");
      observer.observe(items[k]);
    }
  }

});