import 'package:flutter/material.dart';
import 'package:absensi_mobile/services/auth_service.dart';
import 'package:cool_alert/cool_alert.dart';
import 'dashboard_page.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final emailController = TextEditingController();
  final passwordController = TextEditingController();
  bool isLoading = false;

  void login() async {
    if (emailController.text.isEmpty || passwordController.text.isEmpty) {
      CoolAlert.show(
        context: context,
        type: CoolAlertType.warning,
        text: 'Email dan password tidak boleh kosong!',
      );
      return;
    }

    setState(() => isLoading = true);

    final result = await AuthService.login(
      emailController.text.trim(),
      passwordController.text.trim(),
    );

    setState(() => isLoading = false);

    print('Login Result: $result'); // Debugging hasil login

    if (result['success']) {
      CoolAlert.show(
  context: context,
  type: CoolAlertType.success,
  text: 'Login berhasil!',
  autoCloseDuration: const Duration(seconds: 2),
);

Future.delayed(const Duration(seconds: 2), () {
  Navigator.pushReplacement(
    context,
    MaterialPageRoute(builder: (_) => const DashboardPage()),
  );
});
    } else {
      CoolAlert.show(
        context: context,
        type: CoolAlertType.error,
        title: 'Login Gagal',
        text: result['message'] ?? 'Username atau password salah.',
        autoCloseDuration: const Duration(seconds: 3),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Padding(
        padding: const EdgeInsets.all(24),
        child: Center(
          child: SingleChildScrollView(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Image.asset(
                  'assets/images/logo_konawe.png',
                  height: 100,
                ),
                const SizedBox(height: 20),
                const Text(
                  "Login",
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Colors.black,
                  ),
                ),
                const SizedBox(height: 40),
                TextField(
                  controller: emailController,
                  decoration: const InputDecoration(
                    hintText: 'NIP',
                    border: UnderlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 20),
                TextField(
                  controller: passwordController,
                  obscureText: true,
                  decoration: const InputDecoration(
                    hintText: 'Password',
                    border: UnderlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 40),
                isLoading
                    ? const CircularProgressIndicator()
                    : ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color.fromARGB(255, 92, 30, 235),
                          minimumSize: const Size(double.infinity, 48),
                        ),
                        onPressed: login,
                        child: const Text(
                          "LOGIN",
                          style: TextStyle(color: Colors.white),
                        ),
                      ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
