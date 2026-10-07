import java.io.*; import jakarta.servlet.*; import jakarta.servlet.http.*;
public class LoginServlet extends HttpServlet{
 protected void doPost(HttpServletRequest req,HttpServletResponse res)throws IOException{
  HttpSession session=req.getSession(); session.setAttribute("username",req.getParameter("username"));
  res.sendRedirect("WelcomeServlet");
 }
}