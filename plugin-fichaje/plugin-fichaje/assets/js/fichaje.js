(function($){'use strict';
function updateClock(){var $el=$('#pf-clock-live');if(!$el.length)return;var n=new Date();$el.text(String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0'));}
function updateElapsed(){$('.pf-elapsed[data-since]').each(function(){var s=parseInt($(this).data('since'),10)*1000,e=Date.now()-s;if(e<0)return;var h=Math.floor(e/3600000),m=Math.floor((e%3600000)/60000),sc=Math.floor((e%60000)/1000);$(this).text('('+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(sc).padStart(2,'0')+')')});}
function showMsg($el,msg,type){$el.text(msg).removeClass('success error').addClass(type).show();}
function toMysql(v){return v?v.replace('T',' ')+':00':'';}
$(document).on('click','#pf-main-btn',function(){
    var $btn=$(this),action=$btn.data('action'),$msg=$('#pf-response-msg');
    $btn.prop('disabled',true).text(pfAjax.i18n.confirming);$msg.hide().removeClass('success error');
    $.post(pfAjax.ajaxurl,{action:action,nonce:pfAjax.nonce}).done(function(res){
        if(res.success){showMsg($msg,res.data.message,'success');setTimeout(function(){location.reload();},1500);}
        else{showMsg($msg,res.data.message,'error');$btn.prop('disabled',false).text(action==='pf_clock_in'?pfAjax.i18n.clock_in:pfAjax.i18n.clock_out);}
    }).fail(function(){showMsg($msg,pfAjax.i18n.error,'error');$btn.prop('disabled',false);});
});
$('#pf-manual-form').on('submit',function(e){
    e.preventDefault();var $f=$(this),$msg=$f.find('.pf-form-msg'),$btn=$f.find('[type=submit]');
    $btn.prop('disabled',true);$msg.text('').removeClass('success error');
    $.post(pfAjax.ajaxurl,{action:'pf_admin_create',nonce:pfAjax.nonce,user_id:$f.find('[name=user_id]').val(),hora_entrada:toMysql($f.find('[name=hora_entrada]').val()),hora_salida:toMysql($f.find('[name=hora_salida]').val()),notas:$f.find('[name=notas]').val(),motivo:$f.find('[name=motivo]').val()
    }).done(function(res){
        if(res.success){showMsg($msg,res.data.message,'success');$f[0].reset();setTimeout(function(){location.reload();},1500);}
        else{showMsg($msg,res.data.message,'error');}
    }).fail(function(){showMsg($msg,pfAjax.i18n.error,'error');}).always(function(){$btn.prop('disabled',false);});
});
$(document).on('click','.pf-btn-edit',function(){
    var id=$(this).data('id');
    $.post(pfAjax.ajaxurl,{action:'pf_admin_get_record',nonce:pfAjax.nonce,record_id:id}).done(function(res){
        if(!res.success){alert(res.data.message);return;}
        var r=res.data;
        $('#pf-edit-id').val(r.id);
        $('#pf-edit-entrada').val(r.hora_entrada?r.hora_entrada.replace(' ','T').substring(0,16):'');
        $('#pf-edit-salida').val(r.hora_salida?r.hora_salida.replace(' ','T').substring(0,16):'');
        $('#pf-edit-notas').val(r.notas||'');$('#pf-edit-motivo').val('');
        $('#pf-edit-modal').fadeIn(150);
    });
});
$('#pf-edit-form').on('submit',function(e){
    e.preventDefault();var $f=$(this),$msg=$f.find('.pf-form-msg'),$btn=$f.find('[type=submit]');
    $btn.prop('disabled',true);
    $.post(pfAjax.ajaxurl,{action:'pf_admin_edit',nonce:pfAjax.nonce,record_id:$('#pf-edit-id').val(),hora_entrada:toMysql($('#pf-edit-entrada').val()),hora_salida:toMysql($('#pf-edit-salida').val()),notas:$('#pf-edit-notas').val(),motivo:$('#pf-edit-motivo').val()
    }).done(function(res){
        if(res.success){showMsg($msg,res.data.message,'success');setTimeout(function(){location.reload();},1200);}
        else{showMsg($msg,res.data.message,'error');$btn.prop('disabled',false);}
    }).fail(function(){showMsg($msg,pfAjax.i18n.error,'error');$btn.prop('disabled',false);});
});
$(document).on('click','.pf-btn-delete',function(){
    $('#pf-delete-id').val($(this).data('id'));$('#pf-delete-motivo').val('');$('#pf-delete-modal').fadeIn(150);
});
$('#pf-delete-form').on('submit',function(e){
    e.preventDefault();var $f=$(this),$msg=$f.find('.pf-form-msg'),$btn=$f.find('[type=submit]');
    $btn.prop('disabled',true);
    $.post(pfAjax.ajaxurl,{action:'pf_admin_delete',nonce:pfAjax.nonce,record_id:$('#pf-delete-id').val(),motivo:$('#pf-delete-motivo').val()
    }).done(function(res){
        if(res.success){showMsg($msg,res.data.message,'success');setTimeout(function(){location.reload();},1200);}
        else{showMsg($msg,res.data.message,'error');$btn.prop('disabled',false);}
    }).fail(function(){showMsg($msg,pfAjax.i18n.error,'error');$btn.prop('disabled',false);});
});
$(document).on('click','.pf-modal-close',function(){$(this).closest('.pf-modal').fadeOut(120);});
$(document).on('click','.pf-modal',function(e){if($(e.target).hasClass('pf-modal'))$(this).fadeOut(120);});
$(document).on('keydown',function(e){if(e.key==='Escape')$('.pf-modal:visible').fadeOut(120);});
$(function(){updateClock();updateElapsed();setInterval(updateClock,1000);setInterval(updateElapsed,1000);});
})(jQuery);
