window.SiteConfig = window.SiteConfig || {};

function toggleCheckboxField(checkboxId, inputId) {
    const checkbox = document.getElementById(checkboxId);
    const input = document.getElementById(inputId);
    if (!checkbox || !input) return;
    input.style.display = checkbox.checked ? 'block' : 'none';
    if (!checkbox.checked) input.value = '';
}

function toggleSelectGroup(selectId, groupId, triggerValue = 'other') {
    const select = document.getElementById(selectId);
    const group = document.getElementById(groupId);
    if (!select || !group) return;
    group.style.display = select.value === triggerValue ? 'block' : 'none';
}

function toggleOtherUniversity(select) {
    if (!select) return;
    toggleSelectGroup('universitySelect', 'otherUniversityGroup', 'other');
}

function toggleStudentOtherUniversity(select) {
    if (!select) return;
    toggleSelectGroup('studentUniversitySelect', 'studentOtherUniversityGroup', 'other');
}

function toggleAuthor2() {
    toggleCheckboxField('add_author2', 'author2');
}

function toggleAuthor3() {
    toggleCheckboxField('add_author3', 'author3');
}

function showSummary(summaryAr, summaryEn) {
    const notAvailable = window.SiteConfig.notAvailable || 'N/A';
    const arEl = document.getElementById('summary_ar_content');
    const enEl = document.getElementById('summary_en_content');
    if (arEl) arEl.textContent = summaryAr || notAvailable;
    if (enEl) enEl.textContent = summaryEn || notAvailable;
    const modalEl = document.getElementById('summaryModal');
    if (modalEl) new bootstrap.Modal(modalEl).show();
}

function showStudentInfo(name, email, personalEmail, phone, address, additionalInfo, specialty, university, faculty, createdAt) {
    const notAvailable = window.SiteConfig.notAvailable || 'N/A';
    const fields = [
        { id: 'studentInfoName', value: name },
        { id: 'studentInfoEmail', value: email },
        { id: 'studentInfoPhone', value: phone },
        { id: 'studentInfoAddress', value: address },
        { id: 'studentInfoAdditional', value: additionalInfo },
        { id: 'student_name', value: name },
        { id: 'student_email', value: email },
        { id: 'student_personal_email', value: personalEmail },
        { id: 'student_phone', value: phone },
        { id: 'student_address', value: address },
        { id: 'student_additional_info', value: additionalInfo },
        { id: 'student_specialty', value: specialty },
        { id: 'student_university', value: university },
        { id: 'student_faculty', value: faculty },
        { id: 'student_created_at', value: createdAt }
    ];
    fields.forEach(field => {
        const el = document.getElementById(field.id);
        if (el) el.textContent = field.value || notAvailable;
    });
    const modalEl = document.getElementById('studentInfoModal');
    if (modalEl) new bootstrap.Modal(modalEl).show();
}

function editBook(id, title, author, supervisor, specialty, studyShift, pubDate, academicYear, beneficiary, summaryAr, summaryEn) {
    const authors = author.split('، ');
    const valueMap = {
        edit_id: id,
        edit_title: title,
        edit_author1: authors[0] || '',
        edit_author2: authors[1] || '',
        edit_author3: authors[2] || '',
        edit_supervisor_name: supervisor,
        edit_specialty: specialty,
        edit_study_shift: studyShift,
        edit_pub_date: pubDate,
        edit_academic_year: academicYear,
        edit_beneficiary: beneficiary,
        edit_summary_ar: summaryAr,
        edit_summary_en: summaryEn
    };
    Object.keys(valueMap).forEach(key => {
        const el = document.getElementById(key);
        if (el) el.value = valueMap[key];
    });
}

function editPassword(id, name) {
    const userId = document.getElementById('edit_user_id');
    const userName = document.getElementById('edit_user_name');
    if (userId) userId.value = id;
    if (userName) userName.textContent = name;
    const modalEl = document.getElementById('editPasswordModal');
    if (modalEl) new bootstrap.Modal(modalEl).show();
}

function approveProject(id, studentId) {
    const message = window.SiteConfig.confirmApproveProject || 'Are you sure you want to approve this project?';
    if (confirm(message)) {
        window.location.href = '?approve_project=' + id + '&student_id=' + studentId;
    }
}

function rejectProject(id, studentId) {
    const message = window.SiteConfig.confirmRejectProject || 'Are you sure you want to reject this project?';
    if (confirm(message)) {
        window.location.href = '?reject_project=' + id + '&student_id=' + studentId;
    }
}

function updateDateTime() {
    const dateText = document.getElementById('dateText');
    const timeText = document.getElementById('timeText');
    if (!dateText || !timeText) return;
    const now = new Date();
    const locale = window.SiteConfig.locale || 'en-US';
    const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    dateText.textContent = now.toLocaleDateString(locale, dateOptions);
    timeText.textContent = now.toLocaleTimeString(locale, { hour12: false });
}

function initSearchInput() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.trim().toLowerCase();
        const projectCards = document.querySelectorAll('.project-card');
        if (projectCards.length) {
            let visibleCount = 0;
            projectCards.forEach(card => {
                const title = card.getAttribute('data-title')?.toLowerCase() || '';
                const author = card.getAttribute('data-author')?.toLowerCase() || '';
                const supervisor = card.getAttribute('data-supervisor')?.toLowerCase() || '';
                const visible = title.includes(searchTerm) || author.includes(searchTerm) || supervisor.includes(searchTerm) || searchTerm === '';
                card.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });
            let noResultsDiv = document.getElementById('noResults');
            if (visibleCount === 0 && searchTerm !== '') {
                if (!noResultsDiv) {
                    noResultsDiv = document.createElement('div');
                    noResultsDiv.id = 'noResults';
                    noResultsDiv.className = 'no-projects';
                    noResultsDiv.textContent = window.SiteConfig.noResultsText || 'لا توجد نتائج.';
                    const container = document.querySelector('.projects-grid');
                    if (container) container.appendChild(noResultsDiv);
                }
            } else if (noResultsDiv) {
                noResultsDiv.remove();
            }
        } else {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                const title = card.querySelector('h5')?.textContent.toLowerCase() || '';
                const author = card.querySelector('p')?.textContent.toLowerCase() || '';
                card.style.display = title.includes(searchTerm) || author.includes(searchTerm) ? '' : 'none';
            });
        }
    });
}

function initPage() {
    if (window.SiteConfig.showProfileModal) {
        const modalEl = document.getElementById('profileModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();
    }
    if (window.SiteConfig.showRequestModal) {
        const modalEl = document.getElementById('requestModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();
    }
    if (document.getElementById('universitySelect')) {
        toggleSelectGroup('universitySelect', 'otherUniversityGroup', 'other');
    }
    if (document.getElementById('studentUniversitySelect')) {
        toggleSelectGroup('studentUniversitySelect', 'studentOtherUniversityGroup', 'other');
    }
    if (document.getElementById('add_author2')) {
        toggleCheckboxField('add_author2', 'author2');
    }
    if (document.getElementById('add_author3')) {
        toggleCheckboxField('add_author3', 'author3');
    }
    if (document.getElementById('dateText')) {
        updateDateTime();
        setInterval(updateDateTime, 1000);
    }
    initSearchInput();
}

document.addEventListener('DOMContentLoaded', initPage);
