(function () {
  const savedTheme = localStorage.getItem("studenthub-theme");
  const preferredTheme = savedTheme === "light" || savedTheme === "dark" ? savedTheme : "dark";

  document.documentElement.setAttribute("data-theme", preferredTheme);

  function updateToggle(toggle) {
    const isLight = document.documentElement.getAttribute("data-theme") === "light";
    toggle.setAttribute("aria-pressed", String(isLight));
    toggle.setAttribute("aria-label", isLight ? "Switch to dark mode" : "Switch to light mode");
    toggle.innerHTML = isLight ? "☾ <span>Dark mode</span>" : "☀ <span>Light mode</span>";
  }

  document.querySelectorAll(".theme-toggle").forEach(function (toggle) {
    updateToggle(toggle);
    toggle.addEventListener("click", function () {
      const currentTheme = document.documentElement.getAttribute("data-theme");
      const nextTheme = currentTheme === "light" ? "dark" : "light";
      document.documentElement.setAttribute("data-theme", nextTheme);
      localStorage.setItem("studenthub-theme", nextTheme);
      updateToggle(toggle);
    });
  });

  const registrationForm = document.querySelector("#registration-form");

  if (registrationForm) {
    const nameInput = registrationForm.querySelector("#full-name");
    const usernameInput = registrationForm.querySelector("#username");
    const emailInput = registrationForm.querySelector("#email");
    const mobileInput = registrationForm.querySelector("#mobile");
    const passwordInput = registrationForm.querySelector("#password");
    const confirmPasswordInput = registrationForm.querySelector("#confirm-password");
    const message = registrationForm.querySelector("#registration-message");

    const patterns = {
      name: /^[A-Za-z][A-Za-z '-]{0,59}$/,
      username: /^[A-Za-z][A-Za-z0-9_.-]{2,29}$/,
      email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
      mobile: /^\+?[0-9]{10,15}$/,
      password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,64}$/
    };

    function validateRegistrationField(input, pattern, errorMessage) {
      input.setCustomValidity(
        input.value && !pattern.test(input.value.trim()) ? errorMessage : ""
      );
    }

    function validateConfirmation() {
      confirmPasswordInput.setCustomValidity(
        confirmPasswordInput.value && confirmPasswordInput.value !== passwordInput.value
          ? "Passwords do not match."
          : ""
      );
    }

    nameInput.addEventListener("input", function () {
      validateRegistrationField(nameInput, patterns.name, "Enter a valid name using letters, spaces, apostrophes, or hyphens.");
    });
    usernameInput.addEventListener("input", function () {
      validateRegistrationField(usernameInput, patterns.username, "Use 3-30 characters, start with a letter, and use only letters, numbers, dots, underscores, or hyphens.");
    });
    emailInput.addEventListener("input", function () {
      validateRegistrationField(emailInput, patterns.email, "Enter a valid email address.");
    });
    mobileInput.addEventListener("input", function () {
      validateRegistrationField(mobileInput, patterns.mobile, "Enter 10 to 15 digits, optionally starting with +.");
    });
    passwordInput.addEventListener("input", function () {
      validateRegistrationField(passwordInput, patterns.password, "Use 8-64 characters with uppercase, lowercase, number, and special character.");
      validateConfirmation();
    });
    confirmPasswordInput.addEventListener("input", validateConfirmation);

    registrationForm.addEventListener("submit", function (event) {
      validateRegistrationField(nameInput, patterns.name, "Enter a valid name using letters, spaces, apostrophes, or hyphens.");
      validateRegistrationField(usernameInput, patterns.username, "Use 3-30 characters, start with a letter, and use only letters, numbers, dots, underscores, or hyphens.");
      validateRegistrationField(emailInput, patterns.email, "Enter a valid email address.");
      validateRegistrationField(mobileInput, patterns.mobile, "Enter 10 to 15 digits, optionally starting with +.");
      validateRegistrationField(passwordInput, patterns.password, "Use 8-64 characters with uppercase, lowercase, number, and special character.");
      validateConfirmation();
      message.textContent = "";

      if (!registrationForm.checkValidity()) {
        event.preventDefault();
        registrationForm.reportValidity();
        return;
      }
    });
  }

  const formStatusMessages = {
    success: {
      text: "Your form was submitted successfully.",
      state: "success"
    },
    invalid: {
      text: "Please review the form fields and try again.",
      state: "error"
    },
    error: {
      text: "We could not save your submission. Please try again later.",
      state: "error"
    },
    duplicate: {
      text: "An account with this email, username, or mobile number already exists.",
      state: "error"
    },
    "duplicate-email": {
      text: "An account with this email address already exists.",
      state: "error"
    },
    "duplicate-username": {
      text: "That username is already taken. Please choose another.",
      state: "error"
    },
    method: {
      text: "This form must be submitted using the form button.",
      state: "error"
    }
  };

  function showFormStatus(formId, messageId) {
    const form = document.getElementById(formId);
    const message = document.getElementById(messageId);
    const status = new URLSearchParams(window.location.search).get("status");
    let messageData = null;

    if (status === "success") {
      messageData = {
        text: formId === "registration-form" ? "Your registration was saved successfully." : "Your message was sent successfully.",
        state: "success"
      };
    } else if (Object.prototype.hasOwnProperty.call(formStatusMessages, status)) {
      messageData = formStatusMessages[status];
    }

    if (!form || !messageData) {
      return;
    }

    message.textContent = messageData.text;
    message.dataset.state = messageData.state;
    message.hidden = false;
    window.history.replaceState({}, document.title, window.location.pathname + window.location.hash);
  }

  showFormStatus("registration-form", "registration-message");
  showFormStatus("contact-form", "contact-message");

  const posterInput = document.querySelector("[data-poster-input]");
  if (posterInput) {
    const posterForm = posterInput.form;
    const posterMessage = posterForm.querySelector("[data-poster-message]");
    const allowedPosterTypes = new Set(["image/jpeg", "image/png", "image/webp"]);
    const maximumPosterSize = 5 * 1024 * 1024;

    function validatePoster() {
      const file = posterInput.files && posterInput.files[0];
      let error = "";

      if (file && !allowedPosterTypes.has(file.type)) {
        error = "Choose a JPEG, PNG, or WebP image.";
      } else if (file && file.size > maximumPosterSize) {
        error = "The poster must be 5 MB or smaller.";
      }

      posterInput.setCustomValidity(error);
      posterMessage.textContent = error || "Allowed: JPEG, PNG, or WebP up to 5 MB.";
      posterMessage.dataset.state = error ? "error" : "success";
      return !error;
    }

    posterInput.addEventListener("change", validatePoster);
    posterForm.addEventListener("submit", function (event) {
      if (!validatePoster()) {
        event.preventDefault();
        posterInput.reportValidity();
      }
    });
  }

  const badgeClasses = new Set(["success", "warning", "info", "danger"]);
  const eventTableBody = document.querySelector("[data-event-list]");
  const eventForm = document.querySelector("[data-event-form]");
  if (eventTableBody && eventForm) {
    const apiUrl = eventTableBody.dataset.apiUrl;
    const csrfToken = eventTableBody.dataset.csrfToken;
    const eventCount = document.querySelector("[data-event-count]");
    const eventStatus = eventForm.querySelector("[data-event-status]");
    const eventAction = eventForm.querySelector("[data-event-action]");
    const eventIdInput = eventForm.querySelector("[data-event-id]");
    const eventSubmit = eventForm.querySelector("[data-event-submit]");
    const eventCancel = eventForm.querySelector("[data-event-cancel]");
    const eventHeading = document.getElementById("event-form-heading");
    const posterPreview = eventForm.querySelector("[data-event-poster-preview]");
    const removePosterControl = eventForm.querySelector("[data-event-remove-poster]");
    const removePoster = eventForm.querySelector("#remove-poster");
    let managedEvents = [];
    let eventRevision = 0;

    function setEventStatus(message, isError) {
      eventStatus.textContent = message;
      eventStatus.dataset.state = isError ? "error" : "success";
      eventStatus.hidden = false;
    }

    async function eventApiRequest(options) {
      const response = await fetch(apiUrl, options);
      let payload;
      try {
        payload = await response.json();
      } catch (error) {
        throw new Error("The event API returned an invalid response.");
      }
      if (!response.ok) {
        throw new Error(payload && typeof payload.error === "string" ? payload.error : "The event request failed.");
      }
      return payload;
    }

    function createEventTableCell(text) {
      const cell = document.createElement("td");
      cell.textContent = text;
      return cell;
    }

    function renderManagedEvents() {
      eventTableBody.replaceChildren();
      if (managedEvents.length === 0) {
        const row = document.createElement("tr");
        const cell = createEventTableCell("No events have been added.");
        cell.colSpan = 6;
        row.append(cell);
        eventTableBody.append(row);
      }

      managedEvents.forEach(function (item) {
        const row = document.createElement("tr");
        row.append(createEventTableCell(item.title));

        const categoryCell = document.createElement("td");
        const badge = createElement("span", "badge " + (badgeClasses.has(item.badge_class) ? item.badge_class : "info"), item.category);
        categoryCell.append(badge);
        row.append(categoryCell);
        row.append(createEventTableCell(
          formatDate(item.event_date, { day: "numeric", month: "short", year: "numeric" }) + "\n" + item.event_time
        ));
        row.append(createEventTableCell(item.location));
        row.append(createEventTableCell(item.poster_filename ? "Uploaded" : "None"));

        const actionsCell = document.createElement("td");
        const actions = createElement("div", "student-actions");
        const edit = createElement("button", "btn secondary", "Edit");
        edit.type = "button";
        edit.dataset.eventEdit = item.event_id;
        const removeForm = document.createElement("form");
        removeForm.dataset.eventDelete = "";
        removeForm.dataset.eventId = item.event_id;
        const deleteButton = createElement("button", "btn danger-button", "Delete");
        deleteButton.type = "submit";
        removeForm.append(deleteButton);
        actions.append(edit, removeForm);
        actionsCell.append(actions);
        row.append(actionsCell);
        eventTableBody.append(row);
      });

      eventCount.textContent = managedEvents.length + " event" + (managedEvents.length === 1 ? "" : "s") + ".";
    }

    async function loadManagedEvents() {
      const revision = eventRevision;
      const payload = await eventApiRequest({
        method: "GET",
        headers: { Accept: "application/json" }
      });
      if (!payload || !Array.isArray(payload.events)) {
        throw new Error("The event API returned an invalid event list.");
      }
      if (revision !== eventRevision) {
        return;
      }
      managedEvents = payload.events;
      renderManagedEvents();
    }

    function showEventPoster(item) {
      posterPreview.replaceChildren();
      const hasPoster = Boolean(item && typeof item.poster_url === "string" &&
        /^php\/event-poster\.php\?id=\d+&v=[a-f0-9]{48}\.(jpg|png|webp)$/.test(item.poster_url));
      posterPreview.hidden = !hasPoster;
      removePosterControl.hidden = !hasPoster;
      removePoster.checked = false;
      if (hasPoster) {
        posterPreview.append(
          createElement("span", "", "Current poster"),
          Object.assign(createElement("img", "poster-preview"), {
            src: item.poster_url,
            alt: "Current poster for " + item.title
          })
        );
      }
    }

    function resetEventEditor() {
      eventForm.reset();
      eventAction.value = "add";
      eventIdInput.value = "";
      eventForm.querySelector('[name="badge_class"]').value = "info";
      eventSubmit.textContent = "Add Event";
      eventHeading.textContent = "Add Event";
      eventCancel.hidden = true;
      showEventPoster(null);
      eventStatus.hidden = true;
    }

    function editManagedEvent(eventId) {
      const item = managedEvents.find(function (candidate) {
        return candidate.event_id === eventId;
      });
      if (!item) {
        setEventStatus("That event could not be found. Refresh the list and try again.", true);
        return;
      }

      ["title", "category", "event_date", "event_time", "location", "description", "badge_class"].forEach(function (name) {
        eventForm.elements.namedItem(name).value = item[name];
      });
      eventAction.value = "update";
      eventIdInput.value = item.event_id;
      eventSubmit.textContent = "Save Event";
      eventHeading.textContent = "Edit Event";
      eventCancel.hidden = false;
      eventStatus.hidden = true;
      showEventPoster(item);
      eventForm.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    eventForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      if (!eventForm.checkValidity()) {
        eventForm.reportValidity();
        return;
      }

      eventSubmit.disabled = true;
      eventRevision += 1;
      try {
        const payload = await eventApiRequest({
          method: "POST",
          headers: {
            Accept: "application/json",
            "X-CSRF-Token": csrfToken
          },
          body: new FormData(eventForm)
        });
        resetEventEditor();
        setEventStatus(payload.message || "Event saved successfully.", false);
        try {
          await loadManagedEvents();
        } catch (error) {
          console.error("Event saved but the list could not be refreshed:", error);
          setEventStatus((payload.message || "Event saved successfully.") + " Refresh the page to update the list.", true);
        }
      } catch (error) {
        setEventStatus(error.message || "Unable to save the event. Please try again.", true);
      } finally {
        eventSubmit.disabled = false;
      }
    });

    eventCancel.addEventListener("click", resetEventEditor);
    eventTableBody.addEventListener("click", function (event) {
      const editButton = event.target.closest("[data-event-edit]");
      if (editButton) {
        editManagedEvent(editButton.dataset.eventEdit);
      }
    });
    eventTableBody.addEventListener("submit", async function (event) {
      const deleteForm = event.target.closest("[data-event-delete]");
      if (!deleteForm) {
        return;
      }
      event.preventDefault();
      if (!window.confirm("Delete this event? Existing student event registrations will also be removed.")) {
        return;
      }

      const formData = new FormData();
      formData.set("action", "delete");
      formData.set("event_id", deleteForm.dataset.eventId);
      const deleteButton = deleteForm.querySelector('button[type="submit"]');
      deleteButton.disabled = true;
      eventRevision += 1;
      try {
        const payload = await eventApiRequest({
          method: "POST",
          headers: {
            Accept: "application/json",
            "X-CSRF-Token": csrfToken
          },
          body: formData
        });
        if (eventIdInput.value === deleteForm.dataset.eventId) {
          resetEventEditor();
        }
        setEventStatus(payload.message || "Event deleted successfully.", false);
        try {
          await loadManagedEvents();
        } catch (error) {
          console.error("Event deleted but the list could not be refreshed:", error);
          setEventStatus((payload.message || "Event deleted successfully.") + " Refresh the page to update the list.", true);
        }
      } catch (error) {
        setEventStatus(error.message || "Unable to delete the event. Please try again.", true);
      } finally {
        deleteButton.disabled = false;
      }
    });

    loadManagedEvents().catch(function (error) {
      console.error("Unable to load managed events:", error);
      setEventStatus(error.message || "Unable to load events. Please refresh and try again.", true);
    });

    if (eventAction.value === "update" && eventIdInput.value) {
      const initialEvent = managedEvents.find(function (item) {
        return item.event_id === eventIdInput.value;
      });
      if (initialEvent) {
        showEventPoster(initialEvent);
        eventCancel.hidden = false;
      }
    }
  }

  function createElement(tagName, className, text) {
    const element = document.createElement(tagName);
    if (className) {
      element.className = className;
    }
    if (text !== undefined) {
      element.textContent = text;
    }
    return element;
  }

  function formatDate(value, options) {
    return new Intl.DateTimeFormat(undefined, options).format(new Date(value + "T00:00:00"));
  }

  function createContentCard(item, kind) {
    const card = createElement("article", kind === "events" ? "event-card" : "notice-item");
    const badge = createElement("span", "badge " + (badgeClasses.has(item.badge) ? item.badge : "info"), item.category);

    if (kind === "events") {
      const date = new Date(item.date + "T00:00:00");
      const eventTop = createElement("div", "event-top");
      eventTop.append(
        createElement("div", "date", String(date.getDate()).padStart(2, "0")),
        createElement("div", "", formatDate(item.date, { month: "long", year: "numeric" }))
      );

      const body = createElement("div", "event-body");
      if (typeof item.posterUrl === "string" &&
          /^php\/event-poster\.php\?id=\d+&v=[a-f0-9]{48}\.(jpg|png|webp)$/.test(item.posterUrl)) {
        const poster = createElement("img", "event-poster");
        poster.src = item.posterUrl;
        poster.alt = item.title + " event poster";
        poster.loading = "lazy";
        body.append(poster);
      }
      body.append(badge, createElement("h3", "", item.title), createElement("p", "", item.description));

      const location = createElement("p");
      location.append(createElement("strong", "", "Location: "), document.createTextNode(item.location));
      const time = createElement("p");
      time.append(createElement("strong", "", "Time: "), document.createTextNode(item.time));
      body.append(location, time);
      card.append(eventTop, body);
      return card;
    }

    card.append(
      badge,
      createElement("h3", "", item.title),
      createElement("div", "meta", "Published: " + formatDate(item.date, { day: "numeric", month: "long", year: "numeric" })),
      createElement("p", "", item.description)
    );
    return card;
  }

  async function initializeContentList(list) {
    const kind = list.dataset.jsonList;
    const controls = document.querySelector('[data-list-controls="' + kind + '"]');
    const status = list.parentElement.querySelector("[data-list-status]");
    const pagination = list.parentElement.querySelector('[data-pagination="' + kind + '"]');

    if (!controls || !status || !pagination) {
      throw new Error("List controls are missing for " + kind + ".");
    }

    const search = controls.querySelector("[data-search]");
    const category = controls.querySelector("[data-category]");
    const sort = controls.querySelector("[data-sort]");
    const previous = pagination.querySelector("[data-previous]");
    const next = pagination.querySelector("[data-next]");
    const pageLabel = pagination.querySelector("[data-page-label]");
    const pageSize = Number(list.dataset.pageSize);
    let items = [];
    let currentPage = 1;

    if (!search || !category || !sort || !previous || !next || !pageLabel || !Number.isInteger(pageSize) || pageSize < 1) {
      throw new Error("List settings are invalid for " + kind + ".");
    }

    function render() {
      const query = search.value.trim().toLocaleLowerCase();
      const selectedCategory = category.value;
      const filteredItems = items.filter(function (item) {
        const matchesCategory = selectedCategory === "all" || item.category === selectedCategory;
        const searchableText = [item.title, item.description, item.category, item.location, item.time]
          .filter(Boolean)
          .join(" ")
          .toLocaleLowerCase();
        return matchesCategory && searchableText.includes(query);
      });

      filteredItems.sort(function (left, right) {
        if (sort.value === "title-asc") {
          return left.title.localeCompare(right.title);
        }

        const dateOrder = left.date.localeCompare(right.date);
        return sort.value === "date-desc" ? -dateOrder : dateOrder;
      });

      const pageCount = Math.max(1, Math.ceil(filteredItems.length / pageSize));
      currentPage = Math.min(currentPage, pageCount);
      const start = (currentPage - 1) * pageSize;
      const pageItems = filteredItems.slice(start, start + pageSize);
      list.replaceChildren();

      if (pageItems.length === 0) {
        list.append(createElement("p", "list-empty", "No matching " + (kind === "events" ? "events" : "notices") + " found."));
        status.textContent = "No results. Try changing your search or filter.";
      } else {
        pageItems.forEach(function (item) {
          list.append(createContentCard(item, kind));
        });
        status.textContent = "Showing " + (start + 1) + "-" + (start + pageItems.length) + " of " + filteredItems.length + " " + kind + ".";
      }

      pagination.hidden = filteredItems.length === 0;
      pageLabel.textContent = "Page " + currentPage + " of " + pageCount;
      previous.disabled = currentPage === 1;
      next.disabled = currentPage === pageCount;
    }

    try {
      const response = await fetch(list.dataset.source);
      if (!response.ok) {
        throw new Error("Request failed with status " + response.status + ".");
      }

      const data = await response.json();
      const validItems = Array.isArray(data) && data.every(function (item) {
        return item && typeof item.id === "string" &&
          typeof item.title === "string" && item.title.trim() &&
          typeof item.category === "string" && item.category.trim() &&
          typeof item.description === "string" && item.description.trim() &&
          typeof item.date === "string" && /^\d{4}-\d{2}-\d{2}$/.test(item.date) &&
          !Number.isNaN(Date.parse(item.date + "T00:00:00")) &&
          (kind !== "events" || (typeof item.location === "string" && typeof item.time === "string"));
      });

      if (!validItems) {
        throw new Error("The JSON file does not contain valid " + kind + " records.");
      }

      items = data;
      const categories = Array.from(new Set(items.map(function (item) {
        return item.category;
      }))).sort(function (left, right) {
        return left.localeCompare(right);
      });

      categories.forEach(function (value) {
        category.append(createElement("option", "", value));
      });

      [search, category, sort].forEach(function (control) {
        control.addEventListener("input", function () {
          currentPage = 1;
          render();
        });
        control.addEventListener("change", function () {
          currentPage = 1;
          render();
        });
      });
      previous.addEventListener("click", function () {
        currentPage -= 1;
        render();
      });
      next.addEventListener("click", function () {
        currentPage += 1;
        render();
      });
      render();
    } catch (error) {
      console.error("Unable to load " + kind + " data:", error);
      status.textContent = "Unable to load " + kind + ". Run the site through a local web server to enable JSON fetching.";
      list.replaceChildren();
      pagination.hidden = true;
    }
  }

  document.querySelectorAll("[data-json-list]").forEach(function (list) {
    initializeContentList(list).catch(function (error) {
      console.error("Unable to initialize content list:", error);
      const status = list.parentElement.querySelector("[data-list-status]");
      if (status) {
        status.textContent = "Unable to display this list. Check the page setup and try again.";
      }
    });
  });

  window.addEventListener("storage", function (event) {
    if (event.key !== "studenthub-theme") {
      return;
    }

    const nextTheme = event.newValue === "light" ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", nextTheme);
    document.querySelectorAll(".theme-toggle").forEach(updateToggle);
  });
})();
