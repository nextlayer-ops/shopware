;(function ($) {
    'use strict';

    /**
     * Shopware Image Zoom Plugin.
     */
    $.plugin('imageZoom', {

        defaults: {

            /* The css class for the container element which contains the image */
            containerCls: 'js--img-zoom--container',

            /* The css class for the lens element which displays the current zoom viewport */
            lensCls: 'js--img-zoom--lens',

            /* The css class for the container where the zoomed image is viewed */
            flyoutCls: 'js--img-zoom--flyout',

            /* The selector for identifying the active image */
            activeSelector: '.is--active',

            /* The speed for animations in ms */
            animationSpeed: 300
        },

        init: function () {
            var me = this;

            me.applyDataAttributes();

            me.active = false;

            me.$container = me.$el.find('.box--image');
            me.$container.addClass(me.opts.containerCls);

            me.$images = me.$el.find('.image--element');

            me.$thumbnails = me.$el.find('.thumbnail--link');

            me.$flyout = me.createFlyoutElement();
            me.$lens = me.createLensElement();

            me.$activeImageThumbnail = me.getActiveImageThumbnail();
            me.$activeImage = me.getActiveImageElement();

            me.registerEvents();
        },

        registerEvents: function() {
            var me = this;

            me._on(me.$images, 'mouseenter', $.proxy(me.startZoom, me));
            me._on(me.$images, 'mouseleave', $.proxy(me.stopZoom, me));
            me._on(me.$images, 'mousemove', $.proxy(me.onMouseMove, me));

            $.subscribe('plugin/imageSlider/onRightArrowClick', $.proxy(me.stopZoom, me));
            $.subscribe('plugin/imageSlider/onLeftArrowClick', $.proxy(me.stopZoom, me));
            $.subscribe('plugin/imageSlider/onClick', $.proxy(me.stopZoom, me));
            $.subscribe('plugin/imageSlider/onLightbox', $.proxy(me.stopZoom, me));
        },

        createLensElement: function() {
            var me = this;

            return $('<div>', {
                'class': me.opts.lensCls,
                'html': '&nbsp;'
            }).appendTo('body');
        },

        createFlyoutElement: function() {
            var me = this;

            return $('<div>', {
                'class': me.opts.flyoutCls
            }).appendTo(me.$el);
        },

        getActiveImageThumbnail: function() {
            var me = this;

            return me.$thumbnails.filter(me.opts.activeSelector);
        },

        getActiveImageElement: function() {
            var me = this;

            if (!me.$activeImageThumbnail.length) {
                return me.$images.eq(0);
            }

            return me.$images.eq(me.$activeImageThumbnail.index());
        },

        setLensSize: function(width, height) {
            var me = this;

            me.$lens.css({
                'width': width,
                'height': height
            });
        },

        setLensPosition: function(x, y) {
            var me = this;

            me.$lens.css({
                'top': y,
                'left': x
            });
        },

        onMouseMove: function(event) {
            var me = this;

            if (!me.active) {
                me.startZoom();
                return;
            }

            var offset = me.$activeImage.offset(),
                imageWidth = me.$activeImage.innerWidth(),
                imageHeight = me.$activeImage.innerHeight(),
                eventX = event.pageX,
                eventY = event.pageY,
                lensX = eventX - me.lensWidth / 2,
                lensY = eventY - me.lensHeight / 2,
                maxX = offset.left + imageWidth - me.lensWidth,
                maxY = offset.top + imageHeight - me.lensHeight,
                positionX = me.clamp(lensX, offset.left, maxX),
                positionY = me.clamp(lensY, offset.top, maxY),
                zoomX = -(positionX - offset.left) * me.factor,
                zoomY = -(positionY - offset.top) * me.factor;

            me.setLensPosition(positionX, positionY);

            me.$flyout.css('background', 'url(' + me.zoomImageUrl + ') '+ zoomX + 'px ' + zoomY + 'px no-repeat' );
        },

        startZoom: function() {
            var me = this;

            me.$activeImageThumbnail = me.getActiveImageThumbnail();
            me.$activeImageElement = me.getActiveImageElement();
            me.$activeImage = me.$activeImageElement.find('img');

            if (!me.zoomImage) {
                me.zoomImageUrl = me.$activeImageElement.attr('data-img-original');
                me.zoomImage =  new Image();

                me.zoomImage.onload = function() {

                    me.factor = me.zoomImage.width / me.$activeImage.innerWidth();

                    /**
                     * Don't show the lens for small
                     * images where the original size
                     * is smaller as the lens.
                     */
                    if (me.factor <= 1.2) {
                        return;
                    }

                    me.lensWidth = me.$flyout.outerWidth() / me.factor;
                    me.lensHeight = me.$flyout.outerHeight() / me.factor;

                    me.setLensSize(me.lensWidth, me.lensHeight);

                    me.$flyout.css('background', 'url(' + me.zoomImageUrl + ') 0 0 no-repeat' ).fadeIn(me.opts.animationSpeed);
                    me.$lens.fadeIn(me.opts.animationSpeed);
                };

                me.zoomImage.src = me.zoomImageUrl;
            }

            me.active = true;
        },

        stopZoom: function() {
            var me = this;

            me.$lens.fadeOut(me.opts.animationSpeed);
            me.$flyout.fadeOut(me.opts.animationSpeed);

            me.zoomImage = false;
            me.active = false;
        },

        clamp: function(number, min, max) {
            return Math.max(min, Math.min(max, number));
        },

        destroy: function () {
            var me = this;

            me.$lens.remove();
            me.$flyout.remove();
            me.$container.removeClass(me.opts.containerCls);

            me._destroy();
        }
    });
})(jQuery);