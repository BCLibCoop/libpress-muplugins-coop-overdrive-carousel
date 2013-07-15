;(function($) {    

    var _defaults = {
        'viewer_class'          : 'viewer',
        'assoc_text_class'      : 'info',
        'assoc_text_view_class' : 'info_view',
        'absolute_width'        : 450,
        'image_store'           : '/images/',
        'initial_centered_item' : 1,
        'initial_load_duration' : 600,
        'shift_duration'        : 600,
        'item_gap'              : 6,
        'width_center_plus_next_dropshadow'    : 6,
        'item_min_height'       : 100,
        'background_images'     : [],
        'enable_controls'       : true,
        'controls'              : {},
        'controls_class'        : 'control_box',
        'button_width'          : 25,
        'enable_page_counter'   : false,
        'page_counter_class'    : 'pages',
        'debug_level'           : 0        /* 0 = off | > 0 ++ verbose */,
        'debug_filter'          : 'lesser'    /* can be 'lesser', 'exact'  */
    };
    
    $.fn.vpl_carousel = function(method){
        if(methods[method]) {
            return methods[method].apply(this,Array.prototype.slice.call(arguments, 1));
        }
        else if(typeof method === 'object' || ! method ) {
            return methods.init.apply(this,arguments);
        }
        else {
            $.error('Method ['+method+'] not found in carousel');
        }
    };
    
    var _settings,
        _carousel,
        _viewer, 
        _collection,
        _collection_title,
        _covers, 
        _items, 
        _infos,
        _infoview = {};
        
    var _NEXT = 1;
    var _PREV = -1;
    var methods = {
        _msg : function(level,channel,message) {
            if(_settings.debug_filter == 'exact' && _settings.debug_level != level ) return;
            if(_settings.debuig_level>0 && _settings.debug_level<level && _settings.debug_filter != 'any') return; 
            if(!window.console || !console.log) return; // something is not right
            if(channel != '') {
                if( channel == 'debug' )    { eval('console.debug("' + message + '");');}
                else if( channel == 'info') { eval('console.info("' + message + '");'); }
                else if( channel == 'warn') { eval('console.warn("' + message + '");'); }
                else { eval('console.error("' + message + '");'); }
            }
            return;
        },
        init : function(options) { 
            return this.each(function() {
                _settings = $.extend({}, _defaults, options);
                _carousel = this;
                _collection_title = $('.collection_title').first();
                _viewer = $('.'+_settings.viewer_class).first();
                _collection = $('.collection');
                _collection.css('height',_settings.item_min_height+'px');
                $.data(_collection,'current', _settings.initial_centered_item-1);  // the item at this index is the first at center stage with text displayed
                $.data(_collection,'previous', parseInt(_settings.initial_centered_item-2));  // only so it is not null 
                _collection.children().first().addClass('item_before_prev')
                            .next().addClass('item_prev')
                            .next().addClass('item_current')
                            .next().addClass('item_next')
                            .next().addClass('item_after_next');
                _items = _collection.children();
                
              
                $('.item_title_link').each(function(){
                    if($(this).text().length > 50) {
                        var t = $(this).text().substring(0,48) + '...';
                        $(this).text(t);
                    }
                });
                /**************************************/
                
                _infos = _items.children('.'+_settings.assoc_text_class).map(function(){return $(this).detach()});
                
                _covers = _items.children('DIV .cover');
                $.data(_collection,'backgrounds',$(_settings.background_images));
                var _sum = _settings.item_gap * 15;        
                
                var _new_left = -1 * (_viewer.width()/2 + 38);
                                
                var myw = null;
                $.each(_covers,function(i,e){
                    myw = $(e).width();
                    _sum += parseInt(myw + _settings.item_gap);
                    if(i<_settings.initial_centered_item) { _new_left += parseInt(myw + _settings.item_gap); }
                    else if( i == _settings.initial_centered_item) { _new_left += Math.round(parseInt(myw/2)); }
                    myw = null;
                    $(e).find('img').first().css('background',methods._random_background());
                });
                _sum = Math.round(_sum);
                var vw = _viewer.width();
                _infoview = $('.'+_settings.assoc_text_view_class);
                _controls = $('.'+_settings.controls_class).first();
                _controls.children('A').first().bind({
                    mouseover: function(){$(this).not('.disable').toggleClass('active');}, 
                    mouseout:  function(){$(this).not('.disable').toggleClass('active');},
                    click:     function(evt){ evt.preventDefault(); methods.shift(_PREV); }
                })
                .next().bind({
                    mouseover: function(){$(this).not('.disable').toggleClass('active');}, 
                    mouseout:  function(){$(this).not('.disable').toggleClass('active');},
                    click:     function(evt){ evt.preventDefault(); methods.shift(_NEXT); }
                });
                var _pos = _collection.position(); 
                _pos['left'] = _new_left;  
                
                /** ** ** UGLY HACK ** ** **/
                if(ua.browser == 'mozilla') {
                    _pos['left'] = 0;
                    _pos['top'] = 0;
                }
                if(ua.browser == 'chrome') {
                    _pos['left'] = 0;
                    _pos['top'] = 0;
                }                
                /** ** ** UGLY HACK ** ** **/
                 
                _collection.animate( _pos, _settings.initial_load_duration, methods.updateControls );
                $('#new_titles_carousel').removeClass('loading');
            });
        },
        
        shift : function(direction) {
            var request = Math.round($.data(_collection,'current') + direction);
            if(0 > request || request > _items.length-1) return;
            var _prev_pos = {};    // the pos we entered this function with ...
            var _next_pos = {};    // the pos we should leave with ...        
            if(request>0 && ! $.isEmptyObject($('.item_prev'))){
                _prev_pos = $('.item_prev').position();
            }
            if(request < _items.length && ! $.isEmptyObject($('.item_next'))){
                _next_pos = $('.item_next').position();
            }
            $('.item_third').removeClass('item_third');
            $('.item_before_prev').removeClass('item_before_prev');
            $('.item_prev').removeClass('item_prev');
            $('.item_current').removeClass('item_current');
            $('.item_next').removeClass('item_next');
            $('.item_after_next').removeClass('item_after_next');
        
            $.data( _collection,'previous', Math.round($.data(_collection,'current')));
            
            if(request-3>=0) _items.eq(request-3).addClass('item_third');            
            if(request-2>=0) _items.eq(request-2).addClass('item_before_prev');
            if(request-1>=0) _items.eq(request-1).addClass('item_prev');
            _items.eq(request).addClass('item_current');             
            if(request+1<=_items.length) _items.eq(request+1).addClass('item_next');
            if(request+2<=_items.length) _items.eq(request+2).addClass('item_after_next');
            if(request+3<=_items.length) _items.eq(request+3).addClass('item_third');

            $.data(_collection,'current',Math.round(request));
            
            var half_current  = $('.item_current').find('IMG').first().width()/2;   
            var half_previous = 0; // the delta of _collection._items object 0 == left end, 24 == right end
            if(direction>0){
                half_previous = $('.item_prev').find('IMG').first().width()/2;
            }
            else {
                half_previous = $('.item_next').find('IMG').first().width()/2;
            }
            var travel = Math.round(-direction * (half_current + parseInt(_settings.item_gap) + half_previous + parseInt(_settings.width_center_plus_next_dropshadow)));
            var pos = _collection.position(); 
            pos.left += travel; 
            _collection.animate( pos, (_settings.shift_duration*2), methods.updateControls );
        },
        
        _random_background : function () {
            var r = Math.round(Math.random() * 5); // random int range  0-5
            var i = $.data(_collection,'backgrounds')[r];
            return 'transparent url("'+_settings.image_store+'/'+i+'") no-repeat 0 0'; 
        },
        
        updateControls : function() {
            _controls.show();
            if( _settings.enable_controls) {
                _controls.children('.prev').first().toggleClass('disable', !($.data(_collection,'current')>0));
                _controls.children('.next').first().toggleClass('disable', !($.data(_collection,'current')< _items.length));
            }
            if( _settings.enable_page_counter) {
                _controls.children('.'+_settings.page_counter_class).first().empty().text(($.data(_collection,'current')+1)+' of '+_items.length);
            }
            if( _settings.assoc_text_class && _settings.assoc_text_view_class ) {
                if( ! _infos.length ) return; 
                _infoview.fadeOut(_settings.shift_duration,function(){ 
                    _infoview.empty().append(_infos[$.data(_collection,'current')]).fadeIn(_settings.shift_duration);
                    var _ctlbox_top = 0;                    
                    if( jQuery.browser.msie) {    
                        _ctlbox_top = -1* parseInt( 135 + _ctlbox_top + $('.carousel_footer').outerHeight(true) + $('.info_view').outerHeight(true));
                                        }
                                        else {
                                            _ctlbox_top = -1* parseInt( 10 + _ctlbox_top + $('.carousel_footer').outerHeight(true) + $('.info_view').outerHeight(true));
                                        }
                                    _controls.css('top', _ctlbox_top+'px');
                });
            }
        }
    };
})(jQuery);


jQuery().ready(function($){

	var carousel_opts = {
		'initial_centered_item' : 3,
		'initial_load_duration' : 600,
		'shift_duration' : 50,
		'absolute_width' : 466 /* optional spec - limit and maximize carousel in space allotted */,
		'item_gap' : 6 /* empty space inserted between each pair of adjacent covers */,
		'viewer_class' : 'viewport' /* an arbitrary class name used to identify the inner-window object */,
		'assoc_text_class' : 'info' /* an arbitrary class name used to identify the text per-title */,
		'assoc_text_view_class' : 'info_view' /* an arbitrary class name used to identify the presentation area for the per-title text*/,
		'controls_class' : 'control_box' /* an arbitrary class name used to identify the control box overlay */,
		'image_store' : '/styles/imgs' /* URI path fragment - no trailing slash */,
		'width_center_plus_next_dropshadow' : 10,
		/* array of image file names in image_store dir - no leading slash - backgrounds for LI with no cover image */
		'background_images' : ['covers/cover_aqua.jpg',
				'covers/cover_blue.jpg',
				'covers/cover_brown.jpg',
				'covers/cover_green.jpg',
				'covers/cover_purple.jpg',
				'covers/cover_red.jpg']
		}; 

	//alert('init carousel');
	jQuery('#new_titles_carousel').vpl_carousel(window.carousel_opts); 


    ua = $.browser;
    
    /** PROOF & DEBUGGING **/
    
        if(ua.msie) ua.browser = 'msie';
        if(ua.webkit) ua.browser = 'webkit';
        if(ua.mozilla) ua.browser = 'mozilla';
        if(ua.opera) ua.browser = 'opera';
        if(ua.safari) ua.browser = 'safari';
        if(/chrome/.test(navigator.userAgent.toLowerCase())) { 
            ua.chrome = true;
            ua.browser = 'chrome';
        };
        
    
    // alert( ua.browser +': ' + ua.version );
        var re = new RegExp("pubdev1");
        if( re.test(window.location)) {
            $('.site_wide_message').append('<p/>').append('Browser: '+ua.browser+', version: '+ua.version);
        }
    
    
    yepnope([
    {
        test : (ua.msie != true),
        yep   : {'non-ie' : '/styles/carousel_and_slider_styles.css'}, 
        callback: function(u,r,k) {
            if(r) init_our_scripts(k);
        }
    },
    {
        test : ua.msie && ua.version >= 8,
        yep   : {'iegte8' : '/styles/carousel_and_slider_styles.css'}, 
        callback: function(u,r,k) {
            if(r) init_our_scripts(k);
        }
    },
    {
        test : ua.msie && ua.version == 7,
        yep   : {'ie7' : '/styles/carousel_and_slider_styles_IE7.css'}, 
        callback: function(u,r,k) {
            if(r) init_our_scripts(k);
        }
    },
    {
        test : ua.msie && ua.version < 7,
        yep   : {'ielt7' : '/styles/carousel_and_slider_styles_IE_not_supported.css'}
    }
    ]);
});
