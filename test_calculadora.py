
import unittest
from calculadora import soma, subtrai, multiplica, divide

class TestCalculadora(unittest.TestCase):

    def test_soma(self):
        self.assertEqual(soma(2, 3), 5)
        self.assertEqual(soma(-1, 1), 0)

    def test_subtrai(self):
        self.assertEqual(subtrai(5, 3), 2)
        self.assertEqual(subtrai(0, 5), -5)

    def test_multiplica(self):
        self.assertEqual(multiplica(4, 2), 8)
        self.assertEqual(multiplica(0, 10), 0)

    def test_divide(self):
        self.assertEqual(divide(10, 2), 5)
        with self.assertRaises(ValueError):
            divide(10, 0)

if __name__ == "__main__":
    unittest.main()
