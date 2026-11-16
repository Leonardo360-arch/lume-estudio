'use strict';
const client=document.querySelector('#client_id');
const booking=document.querySelector('#booking_id');
client?.addEventListener('change',()=>{booking.value='';for(const option of booking.options){option.hidden=Boolean(option.dataset.client&&option.dataset.client!==client.value);}});
const upload=document.querySelector('[data-upload-form]');
upload?.addEventListener('submit',event=>{
  event.preventDefault();
  const files=upload.querySelector('input[type=file]').files;
  const status=upload.querySelector('[data-upload-status]');
  if(files.length>20||[...files].reduce((sum,file)=>sum+file.size,0)>100*1024*1024){status.textContent='Escolha até 20 fotos e no máximo 100 MB por lote.';return;}
  const button=upload.querySelector('button');const progress=upload.querySelector('progress');
  button.disabled=true;progress.hidden=false;status.textContent='Enviando arquivos…';
  const request=new XMLHttpRequest();request.open('POST',upload.action+'&format=json');request.responseType='json';
  request.upload.onprogress=event=>{if(event.lengthComputable){progress.value=Math.round(event.loaded/event.total*100);status.textContent=`Enviando: ${progress.value}%`;}};
  request.upload.onload=()=>{status.textContent='Envio recebido. Validando e salvando as fotos…';};
  const failed=message=>{status.textContent=message;button.disabled=false;};
  request.onload=()=>{if(request.status>=200&&request.status<300&&request.response?.ok){status.textContent=request.response.message;window.location.assign(request.response.url);}else{failed(request.response?.message||'O envio não foi concluído. Atualize a página e confira as fotos já salvas antes de repetir.');}};
  request.onerror=()=>failed('A conexão falhou. Confira as fotos já salvas antes de repetir o envio.');
  request.send(new FormData(upload));
});
