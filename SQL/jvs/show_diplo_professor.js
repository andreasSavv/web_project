document.addEventListener("DOMContentLoaded", function() {
  console.log("JavaScript Loaded!");  
   const data_display = document.getElementById("data_display");
   
   
function displayThesis(thesisArray) {
  // Καθαρίζουμε το περιεχόμενο
  data_display.innerHTML = "";

  // Δημιουργία card wrapper
  const card = document.createElement("div");
  card.classList.add("card", "shadow-sm");

  // Header
  const header = document.createElement("div");
  header.classList.add("card-header");
  header.innerHTML = "<strong>Λίστα Πτυχιακών</strong>";
  card.appendChild(header);

  // Table responsive wrapper
  const tableResponsive = document.createElement("div");
  tableResponsive.classList.add("table-responsive");

  // Πίνακας
  const table = document.createElement("table");
  table.classList.add("table", "table-striped", "mb-0");

  // Thead
  const thead = document.createElement("thead");
  thead.innerHTML = `
    <tr>
      <th>ID Διπλωματικής</th>
      <th>Τίτλος</th>
      <th>Περιγραφή </th>
      <th>Φοιτητής</th>
      <th>Κατάσταση</th>
      <th>Επιβλέπων</th>
      <th>Τελικός Βάθμος</th>
      <th>PDF</th>
      <th>Νημέρτης</th>
    </tr>
  `;
  table.appendChild(thead);

  // Tbody
  const tbody = document.createElement("tbody");

  if (thesisArray.length === 0) {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td colspan="8" class="text-center text-muted">
        Δεν βρέθηκαν πτυχιακές.
      </td>
    `;
    tbody.appendChild(row);
  } else {
    thesisArray.forEach(element => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${element.Thesis_ID}</td>
        <td>${element.Thesis_Title}</td>
        <td>${element.Thesis_Description}</td>
        <td>${element.Thesis_Student}</td>
        <td>${element.Thesis_Status}</td>
        <td>${element.Thesis_Epimelitis}</td>
        <td>${element.Thesis_Final_Grade ?? '-'}</td>
        <td><a href="${element.Thesis_PDF}" target="_blank">Click to See</a></td>
        <td><a href="${element.Nimertis_link}" target="_blank">${element.Nimertis_link}</a></td>
      `;
      tbody.appendChild(row);
    });
  }

  table.appendChild(tbody);
  tableResponsive.appendChild(table);
  card.appendChild(tableResponsive);
  data_display.appendChild(card);
}

function displaytrimelis(array) {
  // Καθαρίζουμε το περιεχόμενο
  data_display.innerHTML = "";

  // Δημιουργία card wrapper
  const card = document.createElement("div");
  card.classList.add("card", "shadow-sm");

  // Header
  const header = document.createElement("div");
  header.classList.add("card-header");
  header.innerHTML = "<strong>Στοιχεία Τριμελούς</strong>";
  card.appendChild(header);

  // Table responsive wrapper
  const tableResponsive = document.createElement("div");
  tableResponsive.classList.add("table-responsive");

  // Πίνακας
  const table = document.createElement("table");
  table.classList.add("table", "table-striped", "mb-0");

  // Thead
  const thead = document.createElement("thead");
  thead.innerHTML = `
    <tr>
      <th>ID Διπλωματικής</th>
      <th>ΑΜ Φοιτητή</th>
      <th>ΑΜ Καθγητή</th>
      <th>Ημερομηνία Τριμελούς</th>
      <th>Κατάσταση Διπλωματικής</th>
    </tr>
  `;
  table.appendChild(thead);

  // Tbody
  const tbody = document.createElement("tbody");

  if (array.length === 0) {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td colspan="5" class="text-center text-muted">
        Δεν βρέθηκαν εγγραφές τριμελούς.
      </td>
    `;
    tbody.appendChild(row);
  } else {
    array.forEach(element => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${element.Thesis_ID}</td>
        <td>${element.Thesis_Student_Number}</td>
        <td>${element.Professor_User_ID}</td>
        <td>${element.Trimelous_Date}</td>
        <td>${element.Invitation_Status}</td>
      `;
      tbody.appendChild(row);
    });
  }

  table.appendChild(tbody);
  tableResponsive.appendChild(table);
  card.appendChild(tableResponsive);
  data_display.appendChild(card);
}



fetch("fetch_thesis_prof.php" )
      .then (response => response.json())  // convert to json
      .then (({ invitation , xronologio , epimelitis , melos_trimelous, thesis_details}) =>{
        // displayThesis(thesis_details);
    document.getElementById("allthesis").addEventListener("click", () => {
     displayThesis(thesis_details); });

    document.getElementById("trimelis").addEventListener("click", () => {
        const thesisIDs = melos_trimelous
         .map(thesis => thesis.Thesis_ID);
         console.log(thesisIDs); 
        const thesis = thesis_details.filter(thesis =>thesisIDs.includes(thesis.Thesis_ID));
         displayThesis(thesis);});

    document.getElementById("epimelitis").addEventListener("click", () => {
        const thesisIDs = epimelitis
         .map(thesis => thesis.Thesis_ID);
        console.log(thesisIDs);
         const thesis = thesis_details.filter(thesis =>thesisIDs.includes(thesis.Thesis_ID));
         displayThesis(thesis);});

    document.getElementById("meloi_trimeloi").addEventListener("click" , () => {
      const pending_thesis =  thesis_details
      .filter(thesis => thesis.Thesis_Status === "pending")
      .map(thesis => thesis.Thesis_ID);
      console.log(pending_thesis);
      const trimelous = invitation.filter(thesis =>pending_thesis.includes(thesis.Thesis_ID));
      console.log(trimelous);
      displaytrimelis(trimelous);
    });

    // document.getElementById("xronologio").addEventListener("click",() =>{
        

    // });
     
    statusselect.addEventListener("change", () => {
     if(statusselect.value === "pending"){
        const pending_thesis =  thesis_details.filter(thesis => thesis.Thesis_Status === "pending");
        
        displayThesis(pending_thesis);
     }
     else if(statusselect.value == "active"){
        const active_thesis = thesis_details.filter(thesis => thesis.Thesis_Status === "active");
         displayThesis(active_thesis);
     }
     else if(statusselect.value == "ready"){
        const ready_thesis = thesis_details.filter(thesis => thesis.Thesis_Status === "ready");
         displayThesis(ready_thesis);
     }
     else if(statusselect.value == "cancel"){
        const cancel_thesis = thesis_details.filter(thesis => thesis.Thesis_Status === "cancel");
         displayThesis(cancel_thesis);
     }
     else if (statusselect.value == "under_review"){
        const under_review_thesis = thesis_details.filter(thesis => thesis.Thesis_Status === "under_review");
        displayThesis(under_review_thesis);
     }
     








       });         
         
      });
    });
