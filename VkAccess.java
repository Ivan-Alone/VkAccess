package ru.ivan_alone.api.vkauthlib;

import java.io.BufferedInputStream;
import java.io.BufferedWriter;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.io.OutputStream;
import java.io.OutputStreamWriter;
import java.io.UnsupportedEncodingException;
import java.net.CookieHandler;
import java.net.CookieManager;
import java.net.HttpCookie;
import java.net.HttpURLConnection;
import java.net.MalformedURLException;
import java.net.URL;
import java.net.URLEncoder;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class VkAccess {

	public static class VkApp {
		private int app_id;
		private String permissions;
		
		public VkApp(int app_id, String permissions) {
			this.app_id = app_id;
			this.permissions = permissions;
		}
		
		public VkApp(int app_id, String[] permissions) {
			this.app_id = app_id;
			this.permissions = String.join(",", permissions);	
		}
		
		public int getID() {
			return this.app_id;
		}
		
		public String getPermissions() {
			return this.permissions;
		}
		
		@Override
		public String toString() {
			return "{\""+this.getClass().getSimpleName()+"\":{\"app_id\":" + app_id + ",\"permissions\":\"" + permissions + "\"}}";
		}
	}

	private String login;
	private String password;
	private VkApp app_obj;

	private String access_token;
	private int user_id;

	private Map<String, Object> vk;

	public VkAccess(VkApp vk_app, String l, String pwd) {
		this.app_obj = vk_app;
		this.login = l;
		this.password = pwd;
		
		vk = new HashMap<String, Object>();
		vk.put("login", this.login);
		vk.put("password", this.password);
		vk.put("app", this.app_obj);
		
		try {
			CookieManager cm = new CookieManager();
			CookieHandler.setDefault(cm);
			
			URL api = new URL("https://oauth.vk.com/authorize?client_id=" + app_obj.getID() + "&scope=" + app_obj.getPermissions() + "&redirect_uri=https://oauth.vk.com/blank.html&display=mobile&v=5.67&response_type=token&revoke=1");
			HttpURLConnection http = fastConfigureConnection((HttpURLConnection) api.openConnection());
			
			String preset = decodeStream(http.getInputStream());
			
			Map<String, List<String>> headerFields = http.getHeaderFields();
			List<String> cookiesHeader = headerFields.get("Set-Cookie");

			if (cookiesHeader != null) {
			    for (String cookie : cookiesHeader) {
			    	cm.getCookieStore().add(null,HttpCookie.parse(cookie).get(0));
			    }               
			}
			
			String ip_h = preg_match_all("<input type=\"hidden\" name=\"ip_h\" value=\"(.+)\" />", preset);
			String lg_h = preg_match_all("<input type=\"hidden\" name=\"lg_h\" value=\"(.+)\" />", preset);
			String to = preg_match_all("<input type=\"hidden\" name=\"to\" value=\"(.+)\">", preset);

			http.disconnect();
			
			
			URL oauth = new URL("https://login.vk.com/?act=login&soft=1&utf8=1");
			http = fastConfigureConnection((HttpURLConnection) oauth.openConnection());
			http.setRequestMethod("POST");

			this.confirmCookies(http, cm);
			
			Map<String, String> post_info = new HashMap<String, String>();
			post_info.put("email", login);
			post_info.put("pass", password);
			post_info.put("_origin", "https://oauth.vk.com");
			post_info.put("ip_h", ip_h);
			post_info.put("lg_h", lg_h);
			post_info.put("to", to);

			OutputStream os = http.getOutputStream();
            BufferedWriter writer = new BufferedWriter(
                    new OutputStreamWriter(os, "UTF-8"));
            writer.write(getPostDataString(post_info));
            writer.flush();
            writer.close();
            os.close();

            String newURL = preg_match_all("<form method=\"post\" action=\"(.+)\">", this.decodeStream(http.getInputStream()));
			http.disconnect();
            
			URL redir_base = new URL(newURL);
			http = fastConfigureConnection((HttpURLConnection) redir_base.openConnection());

			this.confirmCookies(http, cm);
			
			http.setInstanceFollowRedirects(true);
			http.getInputStream();
			
			String access = http.getURL().toString();
			http.disconnect();
			if (access.equals("https://vk.com")) {
				System.err.println("Incorrect login, password or app parameters!");
				return;
			}
			
			String[] infoo = access.split("#")[1].split("&");
			
			for (String iii : infoo) {
				String[] par = iii.split("=");
				vk.put(par[0], par[1]);
			}
			this.access_token = vk.get("access_token").toString();
			this.user_id = Integer.parseInt(vk.get("user_id").toString());
			
		} catch (Exception e) {
			e.printStackTrace();
		}
	}
	
	public Map<String, Object> getVK() {
		return this.vk;
	}

	public String invoke(String func) throws Exception {
		return invoke(func, null);
	}
	
	public String invoke(String func, String par) throws Exception {
		this.isTokenAvalible();
		return getHTTPContents("https://api.vk.com/method/" + func + "?access_token=" + this.access_token + "&v=5.67&" + par);
	}
	
	private void isTokenAvalible() throws Exception {
		if (this.access_token == null) {
			throw new Exception("Access token is unavailable! Check your application, your login and password and create new instance of VkAccess!");
		}
	}
	
	private void confirmCookies(HttpURLConnection http, CookieManager cm) {
		if (cm.getCookieStore().getCookies().size() > 0) {
			String[] cookies = new String[cm.getCookieStore().getCookies().size()];
			int i = 0;
			for (HttpCookie cook : cm.getCookieStore().getCookies()) {
				cookies[i] = cook.toString();
				i++;
			};
		    http.setRequestProperty("Cookie",
		    String.join(";", cookies));    
		}
	}
	
	private String getPostDataString(Map<String, String> params) throws UnsupportedEncodingException {
		StringBuilder result = new StringBuilder();
		boolean first = true;
		for (Map.Entry<String, String> entry : params.entrySet()) {
			if (first)
				first = false;
			else
				result.append("&");

			result.append(URLEncoder.encode(entry.getKey(), "UTF-8"));
			result.append("=");
			result.append(URLEncoder.encode(entry.getValue(), "UTF-8"));
		}

		return result.toString();
	}

	private static HttpURLConnection fastConfigureConnection(HttpURLConnection http) {
		http.addRequestProperty("User-Agent",
				"Mozilla/5.0 (Windows NT 10.0; WOW64; rv:54.0) Gecko/20100101 Firefox/54.0 VkAuthLib/0.0.1 VkAccess/0.0.1");
		http.addRequestProperty("Accept-Language", "ru-ru,ru;q=0.5");
		http.setInstanceFollowRedirects(true);
		http.setDoInput(true);
		http.setDoOutput(true);
		return http;
	}

	private static String preg_match_all(String pattern, String haystack) {
		Pattern p = Pattern.compile(pattern);
		Matcher m = p.matcher(haystack);
		m.find();
		return m.group(1);
	}
	
	private static String getHTTPContents(String url) {
		String r = "";
		HttpURLConnection getter = null;
    	try {
    		getter = (HttpURLConnection)(new URL(url)).openConnection();
    		
    		getter.setRequestProperty("User-Agent", "Mozilla/5.0 (Windows NT 10.0; WOW64; rv:50.0) Gecko/20100101 Firefox/50.0");
    		return decodeStream(getter.getInputStream());
    		
    	} catch (Exception e) {} finally {
            if (getter != null) {
            	getter.disconnect();
            }
        }
		return r;
	}

	private static String decodeStream(InputStream url) {
		InputStream in = new BufferedInputStream(url);
		try (ByteArrayOutputStream result = new ByteArrayOutputStream()) {
			byte[] buffer = new byte[1024];
			int length;
			while ((length = in.read(buffer)) != -1) {
				result.write(buffer, 0, length);
			}
			return result.toString("UTF-8");
		} catch (Exception e) {
		}
		return "";
	}
	
	@Override
	public String toString() {
		return "{\""+this.getClass().getSimpleName()+"\":"+vk.toString().replace("=", ":")+"}";
	}
}
