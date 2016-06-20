data.Toggle = function Toggle(prop) {
	data[prop] = !data[prop];
	return false;
}

data.Action = function Action(action, listid) {
	$("#action").val(action);
	$("#listid").val(listid || "");
	$("#moderationForm")[0].submit();
}

data.Link = function Link(action) {
	window.location = data.formaction + "?action="+action+"&msgid="+data.msgid+"&ctcid="+data.ctcid+"&modid="+data.modid;
	return false;
}


data.Countdown = function Countdown() {
	if (data.stop) {
		return;
	} else if (--data.config.ActionDelay <= 0) {
		data.Action("send",data.listid);
	} else {
		data.$timeout(data.Countdown,1000);	
	}
	
	data.$timeout(function() {data.$scope.$apply();}, 0);
}

var app = angular.module("moderationApp", []);
app.controller("moderationController", function($scope,$timeout) {
        data.$scope = $scope;
    	data.$timeout = $timeout;
    	data.$scope.data = data;
    	
    	window.document.title = data.captionweb;
    	$("#body").html(data.body);
    	$("#editedhtml").html(data.editedhtml);
    	$("#moderationForm").attr("action",data.formaction);
    	
	if (data.action == "sending") {
		data.Countdown();
	}
	
	//tinyMCE.init({mode : "textareas", theme : "simple"});
	
});		