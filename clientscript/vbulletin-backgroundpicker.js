/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.0
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
vB_XHTML_Ready.subscribe(function(){init_background_picker_page("colorpickers","backgroundpicker")});var background_picker;function vB_BackgroundPicker(A){this.bgid=A;this.previewid="";this.inputid="";this.selectobj=null;this.activealbum=null;this.highlightobj=null;this.albumid=0;this.backgroundpicker=false;this.init()}vB_BackgroundPicker.prototype.init=function(){this.backgroundpicker=fetch_object(this.bgid);if(!this.backgroundpicker){return }document.body.appendChild(this.backgroundpicker);this.select_handler=new vB_Select_Overlay_Handler(this.bgid);YAHOO.util.Event.on(this.bgid+"_close","click",this.close_click,this,true);this.selectobj=fetch_object("backgroundpicker_select");if(this.selectobj){this.albumid=this.selectobj.options[this.selectobj.selectedIndex].value;this.activealbum=fetch_object("usercss_background_container_"+this.albumid);YAHOO.util.Event.on(this.selectobj,"change",this.switch_backgrounds,this,true)}var A=fetch_tags(fetch_object(this.bgid),"li");for(var B=0;B<A.length;B++){if(A[B].id&&A[B].id.substr(0,8)=="usercss_"){YAHOO.util.Event.on(A[B].id,"click",this.insert_image);A[B].attachmentid=A[B].id.replace(/usercss_background_image_/,"")}}};vB_BackgroundPicker.prototype.insert_image=function(B){var A=fetch_object(background_picker.inputid);A.value="albumid="+background_picker.albumid+"&attachmentid="+this.attachmentid;background_picker.close()};vB_BackgroundPicker.prototype.switch_backgrounds=function(A){this.activealbum.style.display="none";this.albumid=this.selectobj.options[this.selectobj.selectedIndex].value;this.activealbum=fetch_object("usercss_background_container_"+this.albumid);this.activealbum.style.display=""};vB_BackgroundPicker.prototype.open=function(E){this.toggle_highlight("off");if(E){this.clickid=E;this.inputid=E.replace(/_picker/,"")}var L=fetch_object(this.clickid);var H=YAHOO.util.Dom.getX(L);var B=YAHOO.util.Dom.getY(L)+L.offsetHeight;var D=fetch_object(this.inputid);var A=null;var I=null;var F=D.value.match(/albumid=(\d+)/);if(F){I=F[1]}var K=D.value.match(/attachmentid=(\d+)/);if(K){A=K[1]}if(I&&A){var C=fetch_object("usercss_background_container_"+I);if(C){var J=fetch_tags(C,"li");for(var G=0;G<J.length;G++){if(J[G].id&&J[G].id.substr(0,8)=="usercss_"){if(J[G].attachmentid==A){this.highlightobj=fetch_object("usercss_background_item_"+A);this.toggle_highlight("on");if(this.albumid!=I){for(G=0;G<this.selectobj.options.length;G++){if(this.selectobj.options[G].value==I){this.selectobj.selectedIndex=G;break}}this.activealbum.style.display="none";this.albumid=I;this.activealbum=fetch_object("usercss_background_container_"+this.albumid);this.activealbum.style.display=""}break}}}}}fetch_object("usercss_background_container_"+this.albumid);this.backgroundpicker.style.left=H+"px";this.backgroundpicker.style.top=B+"px";this.backgroundpicker.style.display="";if(H+this.backgroundpicker.offsetWidth>document.body.clientWidth){H-=H+this.backgroundpicker.offsetWidth-document.body.clientWidth;this.backgroundpicker.style.left=H+"px"}this.select_handler.hide()};vB_BackgroundPicker.prototype.toggle_highlight=function(A){if(this.highlightobj){if(A=="on"){YAHOO.util.Dom.addClass(this.highlightobj,"box_selected");YAHOO.util.Dom.removeClass(this.highlightobh,"box")}else{if(A=="off"){YAHOO.util.Dom.addClass(this.highlightobj,"box");YAHOO.util.Dom.removeClass(this.highlightobj,"box_selected")}}}};vB_BackgroundPicker.prototype.close=function(){fetch_object(this.bgid).style.display="none";background_picker.toggle_highlight("off");this.select_handler.show()};vB_BackgroundPicker.prototype.close_click=function(A){this.close()};vB_BackgroundPicker.prototype.getAncestorOrThisByClassName=function(B,A){if(B.className&&B.className==A){return B}else{return YAHOO.util.Dom.getAncestorByClassName(B,A)}};function init_background_picker_page(D,E){var B;if(!background_picker){background_picker=new vB_BackgroundPicker(E);if(!background_picker.backgroundpicker){return }}var A=YAHOO.util.Dom.get(D).getElementsByTagName("input");for(var C=0;C<A.length;C++){if(A[C].id&&YAHOO.util.Dom.hasClass(A[C],"backgroundpicker_input")){B=YAHOO.util.Dom.get(A[C].id+"_picker");if(B&&YAHOO.util.Dom.hasClass(B,"backgroundpicker_picker")){YAHOO.util.Event.on(B,"click",display_background_picker);B.style.display=""}}}}function display_background_picker(){if(typeof (color_picker)!="undefined"){color_picker.close()}background_picker.open(this.id)};