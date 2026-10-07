<%@ page contentType="text/html;charset=UTF-8" %>
<html><body><h1>Registration Details</h1>
<p>Name: <%= request.getParameter("name") %></p>
<p>Email: <%= request.getParameter("email") %></p>
<p>Course: <%= request.getParameter("course") %></p>
</body></html>