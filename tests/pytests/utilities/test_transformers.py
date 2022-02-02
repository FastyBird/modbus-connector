#!/usr/bin/python3

#     Copyright 2021. FastyBird s.r.o.
#
#     Licensed under the Apache License, Version 2.0 (the "License");
#     you may not use this file except in compliance with the License.
#     You may obtain a copy of the License at
#
#         http://www.apache.org/licenses/LICENSE-2.0
#
#     Unless required by applicable law or agreed to in writing, software
#     distributed under the License is distributed on an "AS IS" BASIS,
#     WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#     See the License for the specific language governing permissions and
#     limitations under the License.

# Test dependencies
import unittest

# Library dependencies
from fastybird_metadata.types import DataType

# Library libs
from fastybird_modbus_connector.utilities.transformers import DataTransformHelpers


class TestDataTransformHelpers(unittest.TestCase):
    def test_transform_from_device(self) -> None:
        self.assertEqual(5.0, DataTransformHelpers.transform_from_device(
            data_type=DataType.FLOAT,
            value_format=None,
            value=5,
        ))
        self.assertEqual(1.29, DataTransformHelpers.transform_from_device(
            data_type=DataType.FLOAT,
            value_format=None,
            value=1.29,
        ))

        self.assertEqual(True, DataTransformHelpers.transform_from_device(
            data_type=DataType.BOOLEAN,
            value_format=None,
            value=1,
        ))
        self.assertEqual(False, DataTransformHelpers.transform_from_device(
            data_type=DataType.BOOLEAN,
            value_format=None,
            value=0,
        ))

        self.assertEqual(10, DataTransformHelpers.transform_from_device(
            data_type=DataType.UINT,
            value_format=None,
            value=10,
        ))

        self.assertEqual("off", DataTransformHelpers.transform_from_device(
            data_type=DataType.ENUM,
            value_format=[("on", "1", "100"), ("off", "2", "200"), ("unknown", "3", "300")],
            value=2,
        ))
        self.assertEqual(None, DataTransformHelpers.transform_from_device(
            data_type=DataType.ENUM,
            value_format=[("on", "1", "100"), ("off", "2", "200"), ("unknown", "3", "300")],
            value=200,
        ))
        self.assertEqual("2", DataTransformHelpers.transform_from_device(
            data_type=DataType.ENUM,
            value_format=["1", "2", "3"],
            value=2,
        ))
        self.assertEqual(None, DataTransformHelpers.transform_from_device(
            data_type=DataType.ENUM,
            value_format=["1", "2", "3"],
            value=4,
        ))

    # -----------------------------------------------------------------------------

    def test_transform_to_device(self) -> None:
        self.assertEqual(5.0, DataTransformHelpers.transform_to_device(
            data_type=DataType.FLOAT,
            value_format=None,
            value=5,
        ))
        self.assertEqual(5.0, DataTransformHelpers.transform_to_device(
            data_type=DataType.FLOAT,
            value_format=None,
            value="5",
        ))
        self.assertEqual(5.0, DataTransformHelpers.transform_to_device(
            data_type=DataType.FLOAT,
            value_format=None,
            value="5.0",
        ))

        self.assertEqual(1, DataTransformHelpers.transform_to_device(
            data_type=DataType.BOOLEAN,
            value_format=None,
            value=True,
        ))
        self.assertEqual(None, DataTransformHelpers.transform_to_device(
            data_type=DataType.BOOLEAN,
            value_format=None,
            value="true",
        ))
        self.assertEqual(None, DataTransformHelpers.transform_to_device(
            data_type=DataType.BOOLEAN,
            value_format=None,
            value=1,
        ))

        self.assertEqual(200, DataTransformHelpers.transform_to_device(
            data_type=DataType.ENUM,
            value_format=[("on", "1", "100"), ("off", "2", "200"), ("unknown", "3", "300")],
            value="off",
        ))
        self.assertEqual(None, DataTransformHelpers.transform_to_device(
            data_type=DataType.ENUM,
            value_format=[("on", "1", "100"), ("off", "2", "200"), ("unknown", "3", "300")],
            value=2,
        ))
        self.assertEqual(2, DataTransformHelpers.transform_to_device(
            data_type=DataType.ENUM,
            value_format=["1", "2", "3"],
            value=2,
        ))
        self.assertEqual(2, DataTransformHelpers.transform_to_device(
            data_type=DataType.ENUM,
            value_format=["1", "2", "3"],
            value="2",
        ))
        self.assertEqual(None, DataTransformHelpers.transform_to_device(
            data_type=DataType.ENUM,
            value_format=["1", "2", "3"],
            value=4,
        ))


if __name__ == "__main__":
    unittest.main()
