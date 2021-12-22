
require(['core/ajax'], function(ajax) {
    var promises = ajax.call([{
        methodname: 'graded_users_selector', 
        args: { report: report, course: course, user_id: user_id, group_id: group_id, include_all: include_all } 
    }]);

   promises[0].done(function(response) {
       console.log('Done');
   }).fail(function(ex) {
       // do something with the exception
   });
});