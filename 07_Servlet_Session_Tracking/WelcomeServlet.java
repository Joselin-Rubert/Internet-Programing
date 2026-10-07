import java.io.*; import jakarta.servlet.*; import jakarta.servlet.http.*;
public class WelcomeServlet extends HttpServlet{
 protected void doGet(HttpServletRequest req,HttpServletResponse res)throws IOException{
  HttpSession s=req.getSession(false); res.setContentType("text/html"); PrintWriter out=res.getWriter();
  if(s!=null&&s.getAttribute("username")!=null) out.println("<h1>Welcome "+s.getAttribute("username")+"</h1><p>Session ID: "+s.getId()+"</p>");
  else out.println("<h1>No active session</h1>");
 }
}