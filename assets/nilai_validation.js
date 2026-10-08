document.addEventListener('DOMContentLoaded', function(){
    const inputs = document.querySelectorAll('[data-nilai-akademik]');
    const polaNilai = /^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/;
    const pesan = 'Masukkan angka 0 sampai 100, maksimal tiga digit dan dua angka desimal.';

    inputs.forEach(function(input){
        input.dataset.nilaiTerakhirValid = input.value.trim();
        input.setAttribute('title', pesan);

        input.addEventListener('keydown', function(event){
            if(['-', '+', 'e', 'E'].includes(event.key)){
                event.preventDefault();
            }
        });

        input.addEventListener('input', function(){
            const value = input.value.trim();
            const valid = value === '' ||
                (polaNilai.test(value) && Number(value) >= 0 && Number(value) <= 100);

            if(valid){
                input.dataset.nilaiTerakhirValid = value;
                input.setCustomValidity('');
                return;
            }

            input.value = input.dataset.nilaiTerakhirValid || '';
            input.setCustomValidity('');

            if(typeof input.setSelectionRange === 'function'){
                const posisiAkhir = input.value.length;
                try{
                    input.setSelectionRange(posisiAkhir, posisiAkhir);
                }catch(error){
                }
            }
        });

        input.addEventListener('invalid', function(){
            if(input.validity.rangeOverflow || input.validity.rangeUnderflow ||
               input.validity.badInput){
                input.setCustomValidity(pesan);
            }
        });
    });
});
