<?php echo $form->renderMsgs(); ?>
<?php if (!$form->isFormHidden()): ?>
    <?php echo $form->renderFormTag(url_for('landing_sign_up_user'), array('class' => 'form-container signup-register-form form-container--small-group', 'role' => 'form', 'af-smart-fill' => 'no', 'data-loader-message' => "Gracias por registrarte en Company.")); ?>
        <?php echo $form['_csrf_token']->render(); ?>
        <?php echo $form['profile_type_id']->render(); ?>
        <?php echo $form['landing_type_id']->render(); ?>
        <div class="form-group">
            <?php echo $form['first_name']->renderLabel(); ?>
            <?php echo $form['first_name']->render(array('class' => 'input input-block bottom-margin tooltip', 'title' => 'Ingresá SOLO tu nombre completo como figura en tu documento.')); ?>
        </div>
        <div class="form-group">
            <?php echo $form['last_name']->renderLabel(); ?>
            <?php echo $form['last_name']->render(array('class' => 'input input-block bottom-margin tooltip', 'title' => 'Ingresá SOLO tu apellido completo como figura en tu documento.')); ?>
        </div>
        <div class="form-group">
            <div class="row">
                <div class="col-60-p">
                    <?php echo $form['cuit_number']->renderLabel(); ?>
                    <?php echo $form['cuit_number']->render(array('class' => 'input input-block bottom-margin tooltip tt-center', 'title' => 'Ingresá los 11 números de tu CUIT/CUIL, sin puntos ni comas.'));?>
                </div>
                <div class="col-40-p">
                    <?php echo $form['gender']->renderLabel(); ?>
                    <?php echo $form['gender']->render(array('class' => 'input input-block bottom-margin')); ?>
                </div>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-40-p">
                <?php echo $form['cellphone']->renderLabel(); ?>
                <div class="input-wrap"><span class="number-label">0</span><?php echo $form['cellphone_code']->render(array('class' => 'input tooltip', 'title' => 'Código de area de tu celular')); ?></div>
            </div>
            <div class="col-60-p">
                <?php echo $form['cellphone_code']->renderLabel(); ?>
                <div class="input-wrap"><span class="number-label">15</span><?php echo $form['cellphone']->render(array('class' => 'input tooltip', 'title' => 'N° de celular sin el código de área ni guiones. Ejemplo: 55447788')); ?></div>
            </div>
        </div>
        <div class="form-group">
            <?php echo $form['email']->renderLabel(); ?>
            <?php echo $form['email']->render(array('class' => 'input input-block bottom-margin tooltip', 'title' => 'Ingresá el e-mail que usás habitualmente.')); ?>
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" name="submit-button" class="btn btn-submit btn-large btn-block">Registrate</button>
        </div>
        <div class="form-group">
            <div class="checkbox">
                <?php echo $form['agree_terms']->render(); ?> <?php echo $form['agree_terms']->renderLabel("He leído, comprendo y acepto los <a class=\"link\" href=\"JavaScript:agree_terms_popup('".url_for('legal/get_legal?cod=general_terms_and_conditions')."')\">Términos y Condiciones Generales</a> y <a class=\"link\" href=\"JavaScript:agree_terms_popup('".url_for('legal/get_legal?cod=privacy_policy')."')\">Política de Privacidad</a> para ser miembro de Company."); ?>
            </div>
        </div>
    </form>
<?php endif; ?>
<?php Company_Queue::getJs()->startCapture(); ?>
<script type="text/javascript">

var validated_first_name = null;
var validated_last_name = null;
var validated_cuit_number = null;
var validated_gender = null;
var validated_marital_status = null;
var validated_spouse_cuit_number = null;
var validated_spouse_first_name = null;
var validated_spouse_last_name = null;
var validated_spouse_gender = null;
var validated_email = null;

$(document).ready(function () {

    var url_param = $(location).attr('search');
    if (!$.cookie('COMPANY_LE_LNG_SN'))
    {
        if (url_param == '?r=1')
        {
            // show links
            $('#snooze_tst_links').fadeIn();

            $('#tst_snooze').click( function(e) {
                // prevent click
                e.preventDefault();

                $('#snooze_tst_links').fadeOut();

                // set snooze cookie
                $.cookie('COMPANY_LE_LNG_SN', 1, { expires: 10 });
            });
        };
    };

    // register form
    var forms_config = get_forms_config();
    <?php echo $form->renderFormConfig('forms_config'); ?>
    <?php echo $form->renderFormLoad('forms_config'); ?>

    // JS validators
    // Initialize all the select
    $('#register_ select').afselect();

    // validate names
    AfValidateNames($('#register_first_name'));
    AfValidateNames($('#register_last_name'));

    // CUIT
    $('#register_cuit_number').afinputhelper({
        max_length: 13,
        cuit: true,
        jump_to: '#register_gender',
        validate_func: function(obj, val) {
            if (val.length > 0) {
                var exp = val.split('-');
                if (exp.length != 3 || exp[0].length != 2 || exp[1].length != 8 || exp[2].length != 1) {
                    res = 'no';
                }
                else {
                    res = 'yes';
                }
            }
            else {
                res = 'default';
            }

            return res;
        }
    });

    // validate phones
    AfValidateAreaCode($('#register_cellphone_code'), '#register_cellphone');
    AfValidatePhone($('#register_cellphone'), '#register_email');

    // validate mail
    AfValidateEmail($('#register_email'));

    $('#register_first_name, #register_last_name, #register_cuit_number, #register_gender, #register_email').bind('afvalidate',function(event,res) {
        if ($(this).attr('id') == 'register_first_name')
        {
            validated_first_name = (res == 'yes' ? $(this).val() : null);
        }
        else if ($(this).attr('id') == 'register_last_name')
        {
            validated_last_name = (res == 'yes' ? $(this).val() : null);
        }
        else if ($(this).attr('id') == 'register_cuit_number')
        {
            validated_cuit_number = (res == 'yes' ? $(this).val() : null);
        }
        else if ($(this).attr('id') == 'register_gender')
        {
            validated_gender = (res == 'yes' ? $(this).val() : null);
        }
        else if ($(this).attr('id') == 'register_email')
        {
            validated_email = (res == 'yes' ? $(this).val() : null);
        }

        preload();
    });
});

var timer_ajax_prevent = null;

function preload()
{
    var build_url = '/register_preload';
    if (validated_first_name && validated_first_name.length > 0
        && validated_last_name && validated_last_name.length > 0
        && validated_cuit_number && validated_cuit_number.length > 0
        && validated_gender && validated_gender.length > 0
    ) {
        var profile_type_id = 3;
        if (profile_type_id == 2 || profile_type_id == 3)
        {
            build_url += ('/'+profile_type_id+'/'+(validated_email && validated_email.length > 0 ? '1' : '0')+'/'+validated_cuit_number+'/'+validated_gender+'/'+validated_first_name+'/'+validated_last_name);

            if (validated_marital_status && validated_marital_status.length > 0 && validated_marital_status == 2
                && validated_spouse_cuit_number && validated_spouse_cuit_number.length > 0
                && validated_spouse_first_name && validated_spouse_first_name.length > 0
                && validated_spouse_last_name && validated_spouse_last_name.length > 0
                && validated_spouse_gender && validated_spouse_gender.length > 0
            ) {
                build_url += ('/'+validated_spouse_cuit_number+'/'+validated_spouse_gender+'/'+validated_spouse_first_name+'/'+validated_spouse_last_name);
            }

            build_url = encodeURI(build_url);

            if(timer_ajax_prevent) {
                clearTimeout(timer_ajax_prevent);
                timer_ajax_prevent = null;
            }

            timer_ajax_prevent = setTimeout(function(){

                $.post(build_url, { }, function(data) {
                    if(data.result == false) {
                        fdxForm_ext_error_render($('#register_'),'register['+data.element+']',data.msg);
                    }
                    else {
                        fdxForm_ext_error_hide($('#register_'),'register[cuit_number]');
                        fdxForm_ext_error_hide($('#register_'),'register[gender]');
                        fdxForm_ext_error_hide($('#register_'),'register[spouse_cuit_number]');
                        fdxForm_ext_error_hide($('#register_'),'register[spouse_gender]');
                    }
                },'json');

            },2000);
        }
    }
}

/**
 * Email validator jQuery crap
 */
(function($){

    var configs = {
        form : $('#register_'),
        field : 'register[email]',
        fieldId : '#register_email'
    };

    var EmailValidator = {};
    EmailValidator.Messages = {

        Hide: function() {
            fdxForm_ext_error_hide(configs.form, configs.field);
            $(configs.fieldId).removeClass('default_input_style_blur_warning');
        },

        Error: function(msg) {

            $(configs.fieldId).trigger('af_input_error_show',{
                error_msg: msg,
                auto_focus: false,
                focus_mode: false,
                force_err_init: false
            });
            $(configs.fieldId).removeClass('default_input_style_blur_warning');
        },

        Warning: function(msg) {

            $(configs.fieldId).trigger('af_input_error_show',{
                css_class: 'warning',
                error_msg: msg,
                error_msg_html: true,
                auto_focus: false,
                focus_mode: false,
                force_err_init: false
            });
            $(configs.fieldId).addClass('default_input_style_blur_warning');
        }
    };

    EmailValidator.events = {

        init: function() {
            $(configs.fieldId).bind('afvalidate',function(event,res) {
                if (res == 'default') {
                    EmailValidator.Messages.Hide();
                }
                else if(res == 'no') {
                    EmailValidator.Messages.Error('El e-mail ingresado no tiene un formato valido.');
                }
                else {
                    $.post('<?php echo url_for('register/email_validation'); ?>', {email: $(this).val() }, function(data) {

                        if (!data.mx) {
                            EmailValidator.Messages.Error('No se encontró servidor de correo asociado al dominio '+ data.dominio +'.');
                        }
                        else if(data.warning) {
                            EmailValidator.Messages.Warning('¿Tu e-mail es <span class="suggested_email">'+data.suggestedEmail+'</span>?<br /> Si es correcto presioná sobre la dirección sugerida.');
                        }
                        else {
                            EmailValidator.Messages.Hide();
                        }

                    },'json');
                }
            });

            $(document).on('click', '.suggested_email', function(e) {
                var suggestedEmail = $(this).text();
                $(configs.fieldId).val(suggestedEmail);
                EmailValidator.Messages.Hide();
            });
        }
    };
    EmailValidator.events.init();

}(jQuery));





// ------------------------- javascript below was imported from another file, just for this example. Let it be clear that the code below shouldn't be here :)
// ------------------------- javascript below was imported from another file, just for this example. Let it be clear that the code below shouldn't be here :)
// ------------------------- javascript below was imported from another file, just for this example. Let it be clear that the code below shouldn't be here :)




// validate street name
function AfValidateStreetName(element)
{
    element.afinputhelper({
        max_length: 64,
        validate_regex: /^[0-9a-zñÑüÜáéíóúÁÉÍÓÚ\s]{1,64}$/i,
        filter_func: function (val) {
            var init_val = val;
            val = val.replace(/(^[\s]+|[\s]+$)/g,'');
            val = val.replace(/[^0-9a-zñÑüÜáéíóúÁÉÍÓÚ\s]+/gi,'');
            val = val.replace(/[\s]+/g,' ');
            val = val.replace(/(^[\s]+|[\s]+$)/g,'');
            val = val.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
            return val;
        }
    });
}

// validador de nombres
function AfValidateNames(element)
{
    element.afinputhelper({
        max_length: 64,
        validate_regex: /^[a-zñÑüÜáéíóúÁÉÍÓÚ\s]{1,64}$/i,
        filter_func: function (val) {
            var init_val = val;
            val = val.replace(/(^[\s]+|[\s]+$)/g,'');
            val = val.replace(/[^a-zñÑüÜáéíóúÁÉÍÓÚ\s]+/gi,'');
            val = val.replace(/[\s]+/g,' ');
            val = val.replace(/(^[\s]+|[\s]+$)/g,'');
            val = val.replace(/(^[0-9]+|[0-9]+$)/g,'');
            val = val.replace(/(^[\s]+|[\s]+$)/g,'');
            val = val.replace(/\w\S*/g, function(txt){return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();});
            return val;
        }
    });
}

// validate incoming
function AfValidateIncoming(element)
{
    element.afinputhelper({
        max_length: 5,
        validate_regex: /^[0-9]{0,5}$/i,
        only_numbers: true
    });
}

// validate cuit
function AfValidateCuit(element)
{
    element.afinputhelper({
        max_length: 13,
        cuit: true,
        validate_func: function (obj,val) {
            if (val.length > 0) {
                res = 'yes';
            }
            else {
                res = 'default';
            }

            return res;
        }
    });
}

// validate company and position
function AfValidateCompanyName(element)
{
    element.afinputhelper({
        max_length: 64,
        validate_regex: /^[0-9a-z.,\+-_&ñÑüÜáéíóúÁÉÍÓÚ\s]{3,64}$/i,
        filter_func: function (val) {
            var init_val = val;
            val = val.replace(/(^[\s]+|[\s]+$)/g,'');
            return val;
        }
    });
}

// validate phone numbre
function AfValidatePhone(element, jump_to)
{
    element.afinputhelper({
        max_length: 8,
        only_numbers: true,
        jump_to: jump_to,
        validate_func: function(obj, val) {
            var res;
            var rex = /^[0-9]{6,8}$/;
            if(val.length == 0) {
                res = 'default';
            }
            else if (!rex.test(val)) {
                res = 'no';
                element.trigger('af_input_error_show',{
                    error_msg: 'El número de celular debe tener entre 6 y 8 números'
                });
            }
            else {
                res = 'yes';
            }

            // return
            return res;
        }
    });
}

// validate area code
function AfValidateAreaCode(element, jump_to)
{
    element.afinputhelper({
        max_length: 4,
        only_numbers: true,
        jump_to: jump_to,
        validate_func: function(obj, val) {
            var res;
            var rex = /^[0-9]{2,4}$/;
            if(val.length == 0) {
                res = 'default';
            }
            else if (!rex.test(val)) {
                res = 'no';
                element.trigger('af_input_error_show',{
                    error_msg: 'El código de area es incorrecto.'
                });
            }
            else {
                res = 'yes';
            }

            // return
            return res;
        }
    });
}

// validate zipcode
function AfValidateZipCode(element, jump_to)
{
    element.afinputhelper({
        max_length: 4,
        only_numbers: true,
        jump_to: jump_to,
        validate_func: function(obj, val) {
            var res;
            if(val.length == 0) {
                res = 'default';
            }
            else if (val.length != 4) {
                res = 'no';
                element.trigger('af_input_error_show',{
                    error_msg: 'El Código Postal debe tener 4 caracteres.'
                });
            }
            else {
                res = 'yes';
            }

            // return
            return res;
        }
    });
}

// validate mail format
function AfValidateEmail(element)
{
    element.afinputhelper({
        max_length: 254,
        only_numbers: false,
        validate_func: function(obj, val) {
            var res;
            var rex = /^\s*[\w\-\+_]+(\.[\w\-\+_]+)*\@[\w\-\+_]+\.[\w\-\+_]+(\.[\w\-\+_]+)*\s*$/;

            if(val.length == 0) {
                res = 'default';
            }
            else if (!rex.test(val)) {
                res = 'no';
                element.trigger('af_input_error_show',{
                    error_msg: 'El e-mail ingresado no tiene un formato valido.'
                });
            }
            else {
                res = 'yes';
            }

            // return
            return res;
        }
    });
}

// validate loan title
function AfValidateLoanTitle(element)
{
    element.afinputhelper({
        max_length: 60,
        only_numbers: false,
        validate_func: function(obj, val) {
            var res;
            var rex = /^[0-9a-z.,\+-_&ñÑüÜáéíóúÁÉÍÓÚ\s]{10,60}$/i;

            if(val.length == 0) {
                res = 'default';
            }
            else if (!rex.test(val)) {
                res = 'no';
                if (val.length < 10)
                {
                    element.trigger('af_input_error_show',{
                        error_msg: 'El título del crédito debe tener como mínimo 10 caracteres.',
                        force_err_init: true
                    });
                }
                else if (val.length > 60)
                {
                    element.trigger('af_input_error_show',{
                        error_msg: 'El título del crédito debe tener como máximo 60 caracteres.',
                        force_err_init: true
                    });
                }
                else
                {
                    element.trigger('af_input_error_show',{
                        error_msg: 'El título del crédito es inválido.',
                        force_err_init: true
                    });
                }
            }
            else {
                res = 'yes';
            }

            // return
            return res;
        }
    });
}

// validate loan description
function AfValidateLoanDescription(element)
{
    element.afinputhelper({
        max_length: 500,
        only_numbers: false,
        validate_func: function(obj, val) {
            var res;
            var rex = /^[0-9a-z.,\+-_&ñÑüÜáéíóúÁÉÍÓÚ\s]{35,500}$/i;

            if(val.length == 0) {
                res = 'default';
            }
            else if (!rex.test(val)) {
                res = 'no';
                if (val.length < 35)
                {
                    element.trigger('af_input_error_show',{
                        error_msg: 'La descripción del crédito debe tener como mínimo 35 caracteres.',
                        force_err_init: true
                    });
                }
                else if (val.length > 500)
                {
                    element.trigger('af_input_error_show',{
                        error_msg: 'La descripción del crédito debe tener como máximo 500 caracteres.',
                        force_err_init: true
                    });
                }
                else
                {
                    element.trigger('af_input_error_show',{
                        error_msg: 'La descripción del crédito es inválida.',
                        force_err_init: true
                    });
                }
            }
            else {
                res = 'yes';
            }

            // return
            return res;
        }
    });
}

// validate address number
function AfValidateAddressNumber(element)
{
    element.afinputhelper({
        max_length: 8,
        validate_regex: /^[0-9\/a-zñÑüÜáéíóúÁÉÍÓÚ\s]{1,8}$/i
    });
}

// validate address floor
function AfValidateAddressFloor(element)
{
    element.afinputhelper({
        max_length: 6,
        validate_regex: /^[0-9a-zñÑüÜáéíóúÁÉÍÓÚ\s]{1,6}$/i
    });
}

// validate address department
function AfValidateAddressDepartment(element)
{
    element.afinputhelper({
        max_length: 6,
        validate_regex: /^[0-9a-zñÑüÜáéíóúÁÉÍÓÚ\s]{1,6}$/i,
        only_numbers: false
    });
}

// get cities
function AfGetCities(form_id, element, callback_function)
{
    // address for user
    element.change(function() {

        var city_selected = element.val();
        var target_element = element.parents('div.row').find('select[name$="address_city]"]');

        fdxForm_widget_onchange_suspend(form_id, target_element.attr('name'));
        var params = null;

        if (element.hasClass('signProvinceSelect'))
        {
            params = {
                'province_id': element.val(),
                'is_new_address': 1
            };
        }
        else {
            params = {
                'province_id': element.val()
            };
        }
        if (element.val() > 0)
        {
            target_element.before('<img class="waiting_img" src="/images/ajax_select.gif" width="16" height="11" />');
            $.post('/obtener_ciudades', params,
                function(data, textStatus) {
                    var city_html = '';
                    var n;
                    target_element.siblings('img.waiting_img').remove();
                    target_element.empty();

                    if(city_selected == 245)
                    {
                        target_element.append('<option value="">Elegí barrio<\/option>');
                    }
                    else
                    {
                        target_element.append('<option value="">Elegí localidad<\/option>');
                    }

                    for(n=0; n<data.length; n++)
                    {
                        target_element.append('<option value="'+data[n].id+'">'+data[n].title+'<\/option>');
                    }

                    fdxForm_widget_onchange_resume(form_id, target_element.attr('name'));
                },
            'json');

            // have callback
            if (callback_function)
            {
                callback_function()
            };

        }
        else
        {
            target_element.empty().append('<option value="0">Elegí localidad<\/option>');
            fdxForm_widget_onchange_resume(form_id,target_element.attr('name'));
            fdxForm_widget_redrawed(form_id,target_element.attr('name'));
        }
    });
}

</script>
<?php Company_Queue::getJs()->endCapture(); ?>
