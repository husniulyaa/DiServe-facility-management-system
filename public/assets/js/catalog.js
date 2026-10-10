const searchForm = document.querySelector("#facility-filter");
const searchInput = document.querySelector("#facility-search");
const typeFilter = document.querySelector("#facility-type");
const locationFilter = document.querySelector("#facility-location");
const capacityFilter = document.querySelector("#facility-capacity");
const grid = document.querySelector(".facility-grid");

function filterFacilities() {
  const facilityCards = document.querySelectorAll(".facility-card");
  const searchKeyword = searchInput ? searchInput.value.trim().toLowerCase() : "";
  const selectedType = typeFilter ? typeFilter.value.toLowerCase() : "";
  const selectedLocation = locationFilter ? locationFilter.value.toLowerCase() : "";
  const selectedCapacity = capacityFilter ? capacityFilter.value : "";

  facilityCards.forEach((card) => {
    const name = (card.dataset.name || "").toLowerCase();
    const type = (card.dataset.type || "").toLowerCase();
    const location = (card.dataset.location || "").toLowerCase();
    const capacity = Number(card.dataset.capacity || 0);

    const address = (
      card.querySelector(".facility-card-address")?.textContent || ""
    ).toLowerCase();

    const searchableText = `
            ${name}
            ${type}
            ${location}
            ${capacity}
            ${address}
        `.toLowerCase();

    const matchesSearch =
      searchKeyword === "" || searchableText.includes(searchKeyword);

    const matchesType = selectedType === "" || type === selectedType;

    const matchesLocation =
      selectedLocation === "" || location === selectedLocation;

    let matchesCapacity = true;

    if (selectedCapacity === "0-50") {
      matchesCapacity = capacity <= 50;
    }

    if (selectedCapacity === "51-100") {
      matchesCapacity = capacity >= 51 && capacity <= 100;
    }

    if (selectedCapacity === "101-300") {
      matchesCapacity = capacity >= 101 && capacity <= 300;
    }

    if (selectedCapacity === "301-800") {
      matchesCapacity = capacity >= 301 && capacity <= 800;
    }

    if (selectedCapacity === "801-2000") {
      matchesCapacity = capacity >= 801 && capacity <= 2000;
    }

    if (selectedCapacity === "2001+") {
      matchesCapacity = capacity >= 2001;
    }

    const shouldShow =
      matchesSearch && matchesType && matchesLocation && matchesCapacity;

    card.style.display = shouldShow ? "" : "none";
  });
}

async function loadFacilitiesFromApi() {
  if (!grid) return;
  grid.replaceChildren();
  const loading = document.createElement("p");
  loading.className = "facility-grid-state";
  loading.textContent = "Memuat fasilitas...";
  grid.appendChild(loading);
  const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) ? "http://127.0.0.1:8000" : "";
  try {
    const res = await fetch(`${API_BASE}/api/facilities`);
    if (!res.ok) throw new Error(`Facility request failed (${res.status})`);
    const json = await res.json();
    const facilities = json.data || [];
    grid.replaceChildren();
    if (facilities.length === 0) {
      const empty = document.createElement("p");
      empty.className = "facility-grid-state";
      empty.textContent = "Belum ada fasilitas yang tersedia.";
      grid.appendChild(empty);
      return;
    }

    facilities.forEach((fac) => {
        const card = document.createElement("article");
        card.className = "facility-card";
        card.dataset.name = fac.name;
        card.dataset.type = fac.type;
        card.dataset.location = fac.location;
        card.dataset.capacity = fac.capacity;

        const stLower = (fac.status || "").toLowerCase();
        const isMaint = stLower === "maintenance" || stLower === "dalam perbaikan";
        const isInactive = stLower === "inactive" || stLower === "nonaktif";
        const statusBadgeClass = isMaint ? "maintenance" : (isInactive ? "broken" : "available");
        const statusBadgeText = isMaint ? "Dalam Perbaikan" : (isInactive ? "Nonaktif" : "Tersedia");
        const cardImage = document.createElement("img");
        cardImage.className = "facility-card-image";
        cardImage.alt = fac.name || "Foto fasilitas";
        cardImage.src = fac.image_name
          ? `assets/images/${encodeURIComponent(fac.image_name)}`
          : "assets/images/muladi-dome.png";
        cardImage.addEventListener("error", () => {
          cardImage.src = "assets/images/muladi-dome.png";
        }, { once: true });

        const overlay = document.createElement("div");
        overlay.className = "facility-card-overlay";
        const top = document.createElement("div");
        top.className = "facility-card-top";
        const type = document.createElement("span");
        type.className = "facility-card-type";
        type.textContent = fac.type || "";
        const availability = document.createElement("span");
        availability.className = `facility-card-availability ${statusBadgeClass}`;
        availability.textContent = statusBadgeText;
        top.append(type, availability);

        const content = document.createElement("div");
        content.className = "facility-card-content";
        const title = document.createElement("h3");
        title.className = "facility-card-title";
        title.textContent = fac.name || "";
        const meta = document.createElement("div");
        meta.className = "facility-card-meta";
        const location = document.createElement("span");
        location.textContent = fac.location || "";
        const capacity = document.createElement("span");
        capacity.textContent = `${fac.capacity ?? 0} orang`;
        meta.append(location, capacity);
        const address = document.createElement("p");
        address.className = "facility-card-address";
        address.textContent = fac.address || "";
        const button = document.createElement("button");
        button.type = "button";
        button.className = "facility-card-button";
        button.textContent = "Lihat ketersediaan";
        button.addEventListener("click", () => openAvailability(fac.slug || String(fac.id)));
        content.append(title, meta, address, button);
        card.append(cardImage, overlay, top, content);
        grid.appendChild(card);
    });
    filterFacilities();
  } catch (error) {
    console.error("Error loading facilities:", error);
    const errorState = document.createElement("p");
    errorState.className = "facility-grid-state";
    errorState.textContent = "Fasilitas gagal dimuat. Muat ulang halaman untuk mencoba lagi.";
    grid.replaceChildren(errorState);
  }
}

if (searchForm) {
  searchForm.addEventListener("submit", (event) => {
    event.preventDefault();
    filterFacilities();
  });
}

if (searchInput) {
  searchInput.addEventListener("input", () => {
    filterFacilities();
  });
}

if (typeFilter) {
  typeFilter.addEventListener("change", () => {
    filterFacilities();
  });
}

if (locationFilter) {
  locationFilter.addEventListener("change", () => {
    filterFacilities();
  });
}

if (capacityFilter) {
  capacityFilter.addEventListener("change", () => {
    filterFacilities();
  });
}

loadFacilitiesFromApi();