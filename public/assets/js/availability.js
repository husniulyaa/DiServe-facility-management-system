const availabilityOverlay = document.querySelector("#availability-overlay");
const availabilityClose = document.querySelector("#availability-close");
const availabilityCancel = document.querySelector("#availability-cancel");
const availabilityDate = document.querySelector("#availability-date");
const availabilityFacilityName = document.querySelector(
  "#availability-facility-name",
);
const timeListContainer = document.querySelector(".availability-time-list");
let currentFacilityForAvailability = "";
let availabilityController = null;
let availabilityRequestId = 0;

function localDateString(date = new Date()) {
  const y=date.getFullYear(), m=String(date.getMonth()+1).padStart(2,'0'), d=String(date.getDate()).padStart(2,'0');
  return `${y}-${m}-${d}`;
}
const today = localDateString();
if (availabilityDate) { availabilityDate.min=today; if (!availabilityDate.value) availabilityDate.value=today; }

async function fetchAvailability(facilityName, date) {
  if (date && date < today) { date=today; if (availabilityDate) availabilityDate.value=today; }
  if (!timeListContainer) return;
  availabilityController?.abort();
  availabilityController = new AbortController();
  const requestId = ++availabilityRequestId;
  const state = document.createElement("p");
  state.className = "availability-list-state";
  state.textContent = "Memuat ketersediaan...";
  timeListContainer.replaceChildren(state);
  const API_BASE = (window.location.protocol === "file:" || (window.location.port && window.location.port !== "8000")) ? "http://127.0.0.1:8000" : "";
  try {
    const url = `${API_BASE}/api/facilities/${encodeURIComponent(facilityName)}/availability` + (date ? `?date=${date}` : "");
    const res = await fetch(url, { signal: availabilityController.signal });
    if (!res.ok) throw new Error(`Availability request failed (${res.status})`);
    const data = await res.json();
    if (requestId !== availabilityRequestId) return;

    const slots = data.slots || [];
    if (slots.length === 0) {
      state.textContent = "Tidak ada slot ketersediaan untuk tanggal ini.";
      return;
    }

    const items = slots.map((slot) => {
      const item = document.createElement("div");
      item.className = "availability-time-item";
      const time = document.createElement("span");
      time.className = "availability-time";
      time.textContent = slot.time;
      const status = document.createElement("span");
      status.className = `availability-status ${["available", "pending", "unavailable"].includes(slot.status) ? slot.status : "unavailable"}`;
      status.textContent = slot.status_label;
      item.append(time, status);
      return item;
    });
    timeListContainer.replaceChildren(...items);
  } catch (error) {
    if (error.name === "AbortError") return;
    console.error("Availability fetch error:", error);
    if (requestId === availabilityRequestId) {
      state.textContent = "Ketersediaan gagal dimuat. Pilih tanggal lain atau coba lagi.";
    }
  }
}

function openAvailability(facilityName) {
  currentFacilityForAvailability = facilityName;
  if (availabilityFacilityName) {
    availabilityFacilityName.textContent = facilityName;
  }

  const selectedDate = availabilityDate ? availabilityDate.value : "";
  fetchAvailability(facilityName, selectedDate);

  availabilityOverlay.classList.add("active");
  document.body.style.overflow = "hidden";
}

function closeAvailability() {
  availabilityController?.abort();
  availabilityOverlay.classList.remove("active");
  document.body.style.overflow = "";
}

availabilityClose.addEventListener("click", closeAvailability);
availabilityCancel.addEventListener("click", closeAvailability);

availabilityOverlay.addEventListener("click", function (event) {
  if (event.target === availabilityOverlay) {
    closeAvailability();
  }
});

document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    closeAvailability();
  }
});

if (availabilityDate) {
  availabilityDate.addEventListener("change", function () {
    if (currentFacilityForAvailability) {
      fetchAvailability(currentFacilityForAvailability, availabilityDate.value);
    }
  });
}