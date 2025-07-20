import 'package:intl/intl.dart';

String formatTanggalIndo(String tanggalString, {String format = 'd MMMM yyyy'}) {
  try {
    final DateTime parsedDate = DateTime.parse(tanggalString).toLocal();
    return DateFormat(format, 'id_ID').format(parsedDate);
  } catch (e) {
    print("Error parsing date: $e");
    return tanggalString;
  }
}
