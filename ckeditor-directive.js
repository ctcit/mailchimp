// Directive used to initialise ckEditor RichText plugin
// See http://stackoverflow.com/questions/18917262/updating-textarea-value-with-ckeditor-content-in-angular-js
(function () {
    'use strict';

    angular.module('moderationApp').directive('ckeditor', [function () {
        return {
            require: '?ngModel',
            link: function ($scope, element, attrs, ngModel) {
                var config, ck, updateModel, savedData;

                config = {
                    // CKEditor config
                };

                ck = CKEDITOR.replace(element[0], config);

                if (!ngModel) {
                    return;
                }

                ck.on('instanceReady', function() {
                    var data = ngModel.$viewValue;
                    if (data) {
                        ck.setData(data);
                    } else if (savedData) { 
                        // Hack to handle some weird sort of race problem.
                        // It seems the call to render with valid data
                        // can occur before the instance is ready but
                        // when instanceReady fires the data isn't valid!
                        // So we use the valid data supplied already.
                        ck.setData(savedData);
                    }
                });

                function updateModel() {
                    $scope.$apply(function() {
                        ngModel.$setViewValue(ck.getData());
                    });
                }

                ck.on('change', updateModel);
                ck.on('dataReady', updateModel);
                ck.on('key', updateModel);

                ngModel.$render = function() {
                    var data = ngModel.$viewValue;
                    if (data) {
                        ck.setData(data);
                        savedData = data;
                    }
                };
            }
      };
    }]);
})();