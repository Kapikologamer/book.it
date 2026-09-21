"use strict";

document.addEventListener("DOMContentLoaded", () => {
  const menuButton = document.querySelector("[data-menu-toggle]");
  const menu = document.querySelector("[data-menu]");

  if (menuButton && menu) {
    menuButton.addEventListener("click", () => {
      const isOpen = menu.classList.toggle("is-open");
      menuButton.setAttribute("aria-expanded", String(isOpen));
    });
  }

  const toast = document.createElement("div");
  toast.className = "toast";
  toast.setAttribute("role", "status");
  toast.setAttribute("aria-live", "polite");
  document.body.append(toast);
  let toastTimeout;

  const showDemoMessage = (message) => {
    window.clearTimeout(toastTimeout);
    toast.textContent = message;
    toast.classList.add("is-visible");
    toastTimeout = window.setTimeout(() => toast.classList.remove("is-visible"), 3600);
  };

  document.querySelectorAll("form[data-demo-form]").forEach((form) => {
    form.addEventListener("submit", (event) => {
      event.preventDefault();

      if (!form.reportValidity()) {
        return;
      }

      showDemoMessage(form.dataset.demoMessage || "To jest statyczny prototyp — dane nie zostały zapisane.");
    });
  });

  document.querySelectorAll("[data-demo-action]").forEach((button) => {
    button.addEventListener("click", () => {
      showDemoMessage(button.dataset.demoAction || "Ta funkcja zostanie podłączona w kolejnym etapie.");
    });
  });

  const initialsFromName = (value) => {
    const parts = value.trim().split(/\s+/).filter(Boolean);
    return parts.slice(0, 2).map((part) => part[0].toUpperCase()).join("") || "BI";
  };

  document.querySelectorAll("[data-avatar-group]").forEach((group) => {
    const preview = group.querySelector("[data-avatar-preview]");
    const initials = group.querySelector("[data-avatar-initials]");
    const fileInput = group.querySelector("[data-avatar-input]");
    const removeButton = group.querySelector("[data-avatar-remove]");
    const nameInput = document.querySelector(group.dataset.nameSource || "[data-profile-name]");
    let image = preview ? preview.querySelector("img") : null;

    const refreshInitials = () => {
      if (initials && nameInput) {
        initials.textContent = initialsFromName(nameInput.value);
      }
    };

    const clearAvatar = () => {
      if (image) {
        image.remove();
        image = null;
      }
      if (fileInput) {
        fileInput.value = "";
      }
      if (initials) {
        initials.hidden = false;
      }
      refreshInitials();
    };

    if (nameInput) {
      nameInput.addEventListener("input", refreshInitials);
      refreshInitials();
    }

    if (fileInput && preview) {
      fileInput.addEventListener("change", () => {
        const [file] = fileInput.files;
        if (!file || !file.type.startsWith("image/")) {
          clearAvatar();
          return;
        }

        const reader = new FileReader();
        reader.addEventListener("load", () => {
          if (!image) {
            image = document.createElement("img");
            image.alt = "Lokalny podgląd wybranego avatara";
            preview.append(image);
          }
          image.src = String(reader.result);
          if (initials) {
            initials.hidden = true;
          }
        });
        reader.readAsDataURL(file);
      });
    }

    if (removeButton) {
      removeButton.addEventListener("click", clearAvatar);
    }
  });

  document.querySelectorAll("[data-banner-input]").forEach((input) => {
    input.addEventListener("change", () => {
      const [file] = input.files;
      const target = document.querySelector(input.dataset.bannerInput);
      if (!file || !file.type.startsWith("image/") || !target) {
        return;
      }

      const reader = new FileReader();
      reader.addEventListener("load", () => {
        let image = target.querySelector("img");
        if (!image) {
          image = document.createElement("img");
          image.alt = "Lokalny podgląd bannera firmy";
          target.prepend(image);
        }
        image.src = String(reader.result);
      });
      reader.readAsDataURL(file);
    });
  });

  document.querySelectorAll("[data-day-toggle]").forEach((toggle) => {
    const row = toggle.closest(".day-row");
    const updateRow = () => row?.classList.toggle("is-disabled", !toggle.checked);
    toggle.addEventListener("change", updateRow);
    updateRow();
  });
});
