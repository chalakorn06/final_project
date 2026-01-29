// ฟังก์ชันจัดการ Modal
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// ฟังก์ชันคำนวณราคารวม VAT 7%
function calculateVat(price, vatRate = 0.07) {
    const basePrice = parseFloat(price) || 0;
    const incVat = basePrice * (1 + vatRate);
    return {
        base: basePrice,
        total: incVat,
        vat: incVat - basePrice
    };
}

// ฟังก์ชันแสดงตัวอย่างรูปภาพก่อนอัปโหลด
function previewImage(input, previewId, placeholderId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            const placeholder = document.getElementById(placeholderId);
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            if (placeholder) placeholder.classList.add('hidden');
        }
        reader.readAsDataURL(input.files[0]);
    }
}